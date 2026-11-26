<?php

use App\Modules\Checks\Models\Check;
use App\Modules\Checks\Models\CheckEvent;
use App\Modules\Checks\Services\CheckResultRecorder;
use App\Modules\Groups\Models\Group;
use App\Modules\Incidents\Models\Incident;
use App\Modules\Maintenance\Models\MaintenanceWindow;
use App\Modules\StatusPages\Models\StatusPage;
use App\Modules\StatusPages\Services\StatusPagePayload;
use Illuminate\Support\Facades\Cache;

it('scopes monitors to a group subtree in the payload', function () {
    $acme = Group::factory()->create(['name' => 'Acme']);
    $prod = Group::factory()->create(['name' => 'Production', 'parent_id' => $acme->id, 'color' => '#22AA66']);
    Group::factory()->create(['name' => 'Empty', 'parent_id' => $acme->id]);
    $other = Group::factory()->create(['name' => 'Other client']);
    Check::factory()->create(['name' => 'Acme root', 'group_id' => $acme->id]);
    Check::factory()->create(['name' => 'Acme prod', 'group_id' => $prod->id]);
    Check::factory()->create(['name' => 'Foreign', 'group_id' => $other->id]);
    Check::factory()->create(['name' => 'Paused', 'group_id' => $acme->id, 'enabled' => false]);
    $page = StatusPage::factory()->create(['group_id' => $acme->id]);

    $payload = app(StatusPagePayload::class)->forPage($page->fresh());

    expect($payload['monitors'][0]['name'])->toBe('Acme root')
        ->and($payload['groups'])->toHaveCount(1)
        ->and($payload['groups'][0]['name'])->toBe('Production')
        ->and($payload['groups'][0]['color'])->toBe('#22AA66')
        ->and($payload['groups'][0]['monitors'][0]['name'])->toBe('Acme prod');
});

it('reflects maintenance state and planned windows in the payload', function () {
    $check = Check::factory()->create(['name' => 'Shop', 'failure_threshold' => 1]);
    app(CheckResultRecorder::class)->record($check, false, null, 'boom');
    MaintenanceWindow::factory()->create(['name' => 'Database upgrade', 'check_id' => $check->id]);
    MaintenanceWindow::factory()->create(['name' => 'Unrelated', 'check_id' => Check::factory()->create(['enabled' => false])->id]);
    $page = StatusPage::factory()->create();

    $payload = app(StatusPagePayload::class)->forPage($page);

    expect($payload['overall'])->toBe('maintenance')
        ->and($payload['monitors'][0]['status'])->toBe('maintenance')
        ->and($payload['maintenance'])->toHaveCount(1)
        ->and($payload['maintenance'][0]['name'])->toBe('Database upgrade')
        ->and($payload['maintenance'][0]['active'])->toBeTrue();
});

it('computes 90-day uptime and lists recent incidents', function () {
    $this->travelTo(now()->startOfSecond());
    $check = Check::factory()->create(['name' => 'Blog', 'created_at' => now()->subDays(40)]);
    $check->state()->update(['status' => 'up']);
    CheckEvent::create(['check_id' => $check->id, 'from_status' => 'up', 'to_status' => 'down', 'at' => now()->subDays(20)]);
    CheckEvent::create(['check_id' => $check->id, 'from_status' => 'down', 'to_status' => 'up', 'at' => now()->subDays(20)->addDays(4)]);
    Incident::factory()->resolved()->create(['check_id' => $check->id, 'started_at' => now()->subDays(3)]);
    Incident::factory()->create(['check_id' => $check->id, 'started_at' => now()->subDays(30)]);
    $page = StatusPage::factory()->create();

    $payload = app(StatusPagePayload::class)->forPage($page);

    expect($payload['monitors'][0]['uptime'])->toEqual(90)
        ->and($payload['incidents'])->toHaveCount(1)
        ->and($payload['incidents'][0]['monitor'])->toBe('Blog');

    $page->update(['show_incidents' => false]);
    StatusPagePayload::forget($page->slug);

    $hidden = app(StatusPagePayload::class)->forPage($page->refresh());

    expect($hidden['incidents'])->toHaveCount(0);
});

it('keeps cached payloads separate per slug', function () {
    $a = Group::factory()->create();
    $b = Group::factory()->create();
    Check::factory()->create(['name' => 'Only A', 'group_id' => $a->id]);
    Check::factory()->create(['name' => 'Only B', 'group_id' => $b->id]);
    $pageA = StatusPage::factory()->create(['group_id' => $a->id]);
    $pageB = StatusPage::factory()->create(['group_id' => $b->id]);
    $builder = app(StatusPagePayload::class);

    expect($builder->forPage($pageA)['monitors'][0]['name'])->toBe('Only A')
        ->and($builder->forPage($pageB)['monitors'][0]['name'])->toBe('Only B')
        ->and($builder->forPage($pageB)['monitors'])->toHaveCount(1)
        ->and($builder->forPage($pageA)['monitors'][0]['name'])->toBe('Only A')
        ->and($builder->forPage($pageA)['monitors'])->toHaveCount(1);
});

it('caches the payload for a short time', function () {
    $page = StatusPage::factory()->create();
    Check::factory()->create(['name' => 'First']);
    $builder = app(StatusPagePayload::class);
    $builder->forPage($page);

    Check::factory()->create(['name' => 'Second']);

    expect($builder->forPage($page)['monitors'])->toHaveCount(1);

    StatusPagePayload::forget($page->slug);

    expect($builder->forPage($page)['monitors'])->toHaveCount(2);
});

it('ignores a stored accent colour that is not a hex value', function () {
    $page = StatusPage::factory()->create(['accent_color' => 'red;}</style>']);

    expect(app(StatusPagePayload::class)->forPage($page)['accent_color'])->toBeNull();
});

it('refreshes the payload after cache forget', function () {
    $page = StatusPage::factory()->create(['title' => 'Before']);
    app(StatusPagePayload::class)->forPage($page);

    $page->update(['title' => 'After']);
    StatusPagePayload::forget($page->slug);
    Cache::flush();

    expect(app(StatusPagePayload::class)->forPage($page->fresh())['title'])->toBe('After');
});
