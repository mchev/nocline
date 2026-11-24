<?php

use App\Models\User;
use App\Modules\Updates\Services\DatabaseBackup;
use App\Modules\Updates\Services\ReleaseChecker;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Cache::flush();
    config(['phare.version' => '1.0.0', 'phare.repository' => 'acme/phare', 'phare.update_check' => true]);
});

it('caches the latest GitHub release and reports a newer one', function () {
    Http::fake(['api.github.com/repos/acme/phare/releases/latest' => Http::response([
        'tag_name' => 'v1.2.0', 'html_url' => 'https://github.com/acme/phare/releases/tag/v1.2.0', 'body' => 'Notes', 'published_at' => '2026-11-01T00:00:00Z',
    ])]);

    expect(app(ReleaseChecker::class)->available())->toBeNull();

    $this->artisan('phare:update:check')->assertSuccessful();

    expect(app(ReleaseChecker::class)->available())->toMatchArray(['version' => '1.2.0']);
});

it('reports nothing when already up to date', function () {
    Http::fake(['api.github.com/*' => Http::response(['tag_name' => 'v1.0.0', 'html_url' => 'x'])]);

    $this->artisan('phare:update:check')->assertSuccessful();

    expect(app(ReleaseChecker::class)->available())->toBeNull();
});

it('ignores GitHub errors', function () {
    Http::fake(['api.github.com/*' => Http::response('', 404)]);

    expect(app(ReleaseChecker::class)->refresh())->toBeNull();
});

it('never contacts GitHub when the check is disabled', function () {
    config(['phare.update_check' => false]);
    Http::fake();

    expect(app(ReleaseChecker::class)->refresh())->toBeNull();
    Http::assertNothingSent();
});

it('shows the update to admins only', function () {
    Cache::put('phare:latest-release', ['version' => '2.0.0', 'url' => 'u', 'notes' => '', 'published_at' => null]);

    $this->actingAs(User::factory()->admin()->create())->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->where('update.version', '2.0.0')->where('version', '1.0.0'));

    $this->actingAs(User::factory()->tech()->create())->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->where('update', null));
});

it('flags agents older than the hub', function () {
    $checker = app(ReleaseChecker::class);

    expect($checker->agentIsOutdated('0.9.0'))->toBeTrue()
        ->and($checker->agentIsOutdated('1.0.0'))->toBeFalse()
        ->and($checker->agentIsOutdated('dev'))->toBeFalse()
        ->and($checker->agentIsOutdated(null))->toBeFalse();
});

it('copies the sqlite database file into the backup directory', function () {
    $dir = sys_get_temp_dir().'/phare-bk-'.uniqid();
    $db = tempnam(sys_get_temp_dir(), 'db');
    file_put_contents($db, 'data');
    config(['phare.backup_dir' => $dir, 'database.connections.backup_test' => ['driver' => 'sqlite', 'database' => $db]]);

    $backup = app(DatabaseBackup::class);
    $path = $backup->copy('backup_test');

    expect($backup->supported('backup_test'))->toBeTrue()
        ->and(file_get_contents($path))->toBe('data');
});
