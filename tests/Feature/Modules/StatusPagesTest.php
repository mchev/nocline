<?php

use App\Models\User;
use App\Modules\Checks\Models\Check;
use App\Modules\Checks\Models\CheckEvent;
use App\Modules\Checks\Services\CheckResultRecorder;
use App\Modules\Groups\Models\Group;
use App\Modules\Incidents\Models\Incident;
use App\Modules\Maintenance\Models\MaintenanceWindow;
use App\Modules\StatusPages\Models\StatusPage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

beforeEach(fn () => Cache::flush());

it('shows only names and states on the public page, without login', function () {
    $page = StatusPage::factory()->create(['title' => 'Acme status']);
    $up = Check::factory()->create(['name' => 'Acme website', 'target' => 'https://secret.internal.example', 'failure_threshold' => 1]);
    $down = Check::factory()->create(['name' => 'Acme API', 'target' => '10.0.0.5:8443', 'failure_threshold' => 1]);
    app(CheckResultRecorder::class)->record($up, true);
    app(CheckResultRecorder::class)->record($down, false, null, 'connect ECONNREFUSED 10.0.0.5');

    $response = $this->get(route('status.show', $page->slug))
        ->assertOk()
        ->assertInertia(fn ($p) => $p->component('status/Show')
            ->where('title', 'Acme status')
            ->where('overall', 'degraded')
            ->has('monitors', 2)
            ->where('monitors.0.name', 'Acme API')
            ->where('monitors.0.status', 'down')
            ->where('monitors.1.status', 'operational'));

    $json = json_encode($response->viewData('page')['props']);
    expect($json)->not->toContain('secret.internal')->not->toContain('10.0.0.5')->not->toContain('ECONNREFUSED')
        ->and($json)->not->toContain('"id"')->not->toContain('target');
});

it('serves the same safe data as a json feed', function () {
    $page = StatusPage::factory()->create();
    Check::factory()->create(['name' => 'Site', 'target' => 'https://hidden.example']);

    $this->getJson(route('status.feed', $page->slug))
        ->assertOk()
        ->assertJsonPath('monitors.0.name', 'Site')
        ->assertJsonPath('monitors.0.status', 'unknown');
    expect($this->get(route('status.feed', $page->slug))->getContent())->not->toContain('hidden.example');
});

it('404s for disabled and unknown pages', function () {
    $page = StatusPage::factory()->disabled()->create();

    $this->get(route('status.show', $page->slug))->assertNotFound();
    $this->get(route('status.show', 'nope'))->assertNotFound();
    $this->get(route('status.feed', $page->slug))->assertNotFound();
});

it('scopes the page to a group and its sub-groups as nested sections', function () {
    $acme = Group::factory()->create(['name' => 'Acme']);
    $prod = Group::factory()->create(['name' => 'Production', 'parent_id' => $acme->id, 'color' => '#22AA66']);
    $empty = Group::factory()->create(['name' => 'Empty', 'parent_id' => $acme->id]);
    $other = Group::factory()->create(['name' => 'Other client']);
    Check::factory()->create(['name' => 'Acme root', 'group_id' => $acme->id]);
    Check::factory()->create(['name' => 'Acme prod', 'group_id' => $prod->id]);
    Check::factory()->create(['name' => 'Foreign', 'group_id' => $other->id]);
    Check::factory()->create(['name' => 'Paused', 'group_id' => $acme->id, 'enabled' => false]);
    $page = StatusPage::factory()->create(['group_id' => $acme->id]);

    $response = $this->get(route('status.show', $page->slug))
        ->assertInertia(fn ($p) => $p
            ->has('monitors', 1)->where('monitors.0.name', 'Acme root')
            ->has('groups', 1)
            ->where('groups.0.name', 'Production')->where('groups.0.color', '#22AA66')
            ->where('groups.0.monitors.0.name', 'Acme prod'));

    expect(json_encode($response->viewData('page')['props']))->not->toContain('Foreign')->not->toContain('Other client')->not->toContain('Empty');
});

it('shows maintenance state and planned windows', function () {
    $check = Check::factory()->create(['name' => 'Shop', 'failure_threshold' => 1]);
    app(CheckResultRecorder::class)->record($check, false, null, 'boom');
    MaintenanceWindow::factory()->create(['name' => 'Database upgrade', 'check_id' => $check->id]);
    $unrelated = MaintenanceWindow::factory()->create(['name' => 'Unrelated', 'check_id' => Check::factory()->create(['enabled' => false])->id]);
    $page = StatusPage::factory()->create();

    $this->get(route('status.show', $page->slug))
        ->assertInertia(fn ($p) => $p
            ->where('overall', 'maintenance')
            ->where('monitors.0.status', 'maintenance')
            ->has('maintenance', 1)
            ->where('maintenance.0.name', 'Database upgrade')
            ->where('maintenance.0.active', true));
    expect($unrelated->exists)->toBeTrue();
});

it('computes a 90-day uptime from status transitions and lists recent incidents', function () {
    $this->travelTo(now()->startOfSecond());
    $check = Check::factory()->create(['name' => 'Blog', 'created_at' => now()->subDays(40)]);
    $check->state()->update(['status' => 'up']);
    CheckEvent::create(['check_id' => $check->id, 'from_status' => 'up', 'to_status' => 'down', 'at' => now()->subDays(20)]);
    CheckEvent::create(['check_id' => $check->id, 'from_status' => 'down', 'to_status' => 'up', 'at' => now()->subDays(20)->addDays(4)]);
    Incident::factory()->resolved()->create(['check_id' => $check->id, 'started_at' => now()->subDays(3)]);
    Incident::factory()->create(['check_id' => $check->id, 'started_at' => now()->subDays(30)]);
    $page = StatusPage::factory()->create();

    $this->get(route('status.show', $page->slug))
        ->assertInertia(fn ($p) => $p
            ->where('monitors.0.uptime', 90) // 4 days down out of 40
            ->has('incidents', 1)
            ->where('incidents.0.monitor', 'Blog'));

    $page->update(['show_incidents' => false]);
    Cache::flush();
    $this->get(route('status.show', $page->slug))->assertInertia(fn ($p) => $p->has('incidents', 0));
});

it('keeps cached payloads separate per slug', function () {
    $a = Group::factory()->create();
    $b = Group::factory()->create();
    Check::factory()->create(['name' => 'Only A', 'group_id' => $a->id]);
    Check::factory()->create(['name' => 'Only B', 'group_id' => $b->id]);
    $pageA = StatusPage::factory()->create(['group_id' => $a->id]);
    $pageB = StatusPage::factory()->create(['group_id' => $b->id]);

    $this->get(route('status.show', $pageA->slug))->assertInertia(fn ($p) => $p->where('monitors.0.name', 'Only A'));
    $this->get(route('status.show', $pageB->slug))->assertInertia(fn ($p) => $p->where('monitors.0.name', 'Only B')->has('monitors', 1));
    $this->get(route('status.show', $pageA->slug))->assertInertia(fn ($p) => $p->where('monitors.0.name', 'Only A')->has('monitors', 1));
});

it('caches the payload for a short time', function () {
    $page = StatusPage::factory()->create();
    Check::factory()->create(['name' => 'First']);
    $this->get(route('status.show', $page->slug));

    Check::factory()->create(['name' => 'Second']);

    $this->get(route('status.show', $page->slug))->assertInertia(fn ($p) => $p->has('monitors', 1));
});

it('ignores a stored accent colour that is not a hex value', function () {
    $page = StatusPage::factory()->create(['accent_color' => 'red;}</style>']);

    $this->get(route('status.show', $page->slug))->assertInertia(fn ($p) => $p->where('accent_color', null));
});

it('creates a page with a generated slug and a logo', function () {
    Storage::fake('public');
    $this->actingAs(User::factory()->tech()->create());

    $this->post(route('status-pages.store'), [
        'title' => 'Acme — Service Status',
        'description' => 'Hello',
        'accent_color' => '#22AA66',
        'logo' => UploadedFile::fake()->image('logo.png', 100, 100),
    ])->assertRedirect()->assertSessionHasNoErrors();

    $page = StatusPage::first();
    expect($page->slug)->toBe('acme-service-status')->and($page->enabled)->toBeTrue();
    Storage::disk('public')->assertExists($page->logo_path);
    expect($page->logo_path)->toStartWith('status-pages/');
});

it('rejects svg, non-image and oversized logos', function (UploadedFile $file) {
    Storage::fake('public');
    $this->actingAs(User::factory()->admin()->create());

    $this->post(route('status-pages.store'), ['title' => 'Acme', 'logo' => $file])->assertSessionHasErrors('logo');

    expect(StatusPage::count())->toBe(0)->and(Storage::disk('public')->allFiles())->toBe([]);
})->with([
    'svg' => fn () => UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'),
    'text' => fn () => UploadedFile::fake()->create('logo.png', 5, 'text/plain'),
    'too big' => fn () => UploadedFile::fake()->image('logo.png')->size(2048),
]);

it('replaces and removes the logo file, and deletes it with the page', function () {
    Storage::fake('public');
    $this->actingAs(User::factory()->admin()->create());
    $page = StatusPage::factory()->create(['logo_path' => UploadedFile::fake()->image('old.png')->store('status-pages', 'public')]);
    $old = $page->logo_path;
    $payload = ['title' => $page->title, 'slug' => $page->slug];

    $this->patch(route('status-pages.update', $page), $payload + ['logo' => UploadedFile::fake()->image('new.jpg')])->assertSessionHasNoErrors();
    $page->refresh();
    Storage::disk('public')->assertMissing($old);
    Storage::disk('public')->assertExists($page->logo_path);

    $replaced = $page->logo_path;
    $this->patch(route('status-pages.update', $page), $payload + ['remove_logo' => true]);
    Storage::disk('public')->assertMissing($replaced);
    expect($page->refresh()->logo_path)->toBeNull();

    $page->update(['logo_path' => UploadedFile::fake()->image('again.png')->store('status-pages', 'public')]);
    $kept = $page->logo_path;
    $this->delete(route('status-pages.destroy', $page))->assertRedirect();
    Storage::disk('public')->assertMissing($kept);
    expect(StatusPage::count())->toBe(0);
});

it('keeps slugs unique and url-safe', function () {
    $this->actingAs(User::factory()->tech()->create());
    $existing = StatusPage::factory()->create(['slug' => 'acme']);

    $this->post(route('status-pages.store'), ['title' => 'Another', 'slug' => 'acme'])->assertSessionHasErrors('slug');
    $this->post(route('status-pages.store'), ['title' => 'Another', 'slug' => 'Not Safe/../'])->assertSessionHasErrors('slug');
    $this->patch(route('status-pages.update', $existing), ['title' => 'Same', 'slug' => 'acme'])->assertSessionHasNoErrors();
});

it('refreshes the public page when an admin edits it', function () {
    $this->actingAs(User::factory()->tech()->create());
    $page = StatusPage::factory()->create(['title' => 'Before']);
    $this->get(route('status.show', $page->slug));

    $this->patch(route('status-pages.update', $page), ['title' => 'After', 'slug' => $page->slug]);

    $this->get(route('status.show', $page->slug))->assertInertia(fn ($p) => $p->where('title', 'After'));
});

it('lets viewers read the list but not write, and redirects guests', function () {
    $page = StatusPage::factory()->create();
    $this->get(route('status-pages.index'))->assertRedirect(route('login'));

    $this->actingAs(User::factory()->create());
    $this->get(route('status-pages.index'))->assertOk()
        ->assertInertia(fn ($p) => $p->component('status-pages/Index')->has('pages', 1)->where('pages.0.url', route('status.show', $page->slug)));
    $this->post(route('status-pages.store'), ['title' => 'X'])->assertForbidden();
    $this->patch(route('status-pages.update', $page), ['title' => 'X'])->assertForbidden();
    $this->delete(route('status-pages.destroy', $page))->assertForbidden();
});
