<?php

use App\Models\User;
use App\Modules\Checks\Models\Check;
use App\Modules\Checks\Services\CheckResultRecorder;
use App\Modules\Groups\Models\Group;
use App\Modules\Hosts\Models\Host;
use App\Modules\Metrics\Models\MetricRollup;
use App\Modules\Wallboard\Models\Wallboard;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

it('lets admins regenerate a wallboard url and invalidates the old token', function () {
    ['token' => $old, 'wallboard' => $wallboard] = Wallboard::createWithToken(['name' => 'NOC']);

    $this->actingAs(User::factory()->tech()->create())
        ->post(route('wallboards.regenerate', $wallboard))
        ->assertForbidden();

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('wallboards.regenerate', $wallboard))
        ->assertSessionHas('wallboard_url');

    $url = session('wallboard_url');
    expect($url)->toContain('/wallboard/wb_')
        ->and($url)->not->toContain($old);

    $path = parse_url($url, PHP_URL_PATH);
    $new = basename((string) $path);

    $this->get(route('wallboard.show', $old))->assertNotFound();
    $this->get(route('wallboard.show', $new))->assertOk();
    $this->getJson(route('api.wallboard', $old))->assertNotFound();
    $this->getJson(route('api.wallboard', $new))->assertOk();
});

it('lets only admins create wallboards and shows the url once', function () {
    $this->actingAs(User::factory()->tech()->create())
        ->post(route('wallboards.store'), ['name' => 'NOC'])->assertForbidden();

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('wallboards.store'), ['name' => 'NOC'])
        ->assertSessionHas('wallboard_url');

    expect(Wallboard::count())->toBe(1)
        ->and(Wallboard::first()->token_hash)->toHaveLength(64);
});

it('404s on unknown tokens for both the page and the api', function () {
    $this->get(route('wallboard.show', 'wb_nope'))->assertNotFound();
    $this->getJson(route('api.wallboard', 'wb_nope'))->assertNotFound();
});

it('serves the kiosk page without authentication', function () {
    ['token' => $token] = Wallboard::createWithToken(['name' => 'NOC']);

    $this->get(route('wallboard.show', $token))
        ->assertInertia(fn ($p) => $p->component('wallboard/Show')->where('name', 'NOC'));
});

it('aggregates summary, incidents, failing checks and host metrics', function () {
    ['token' => $token] = Wallboard::createWithToken(['name' => 'NOC']);
    $up = Check::factory()->create();
    $down = Check::factory()->create(['failure_threshold' => 1]);
    app(CheckResultRecorder::class)->record($up, true, 40);
    app(CheckResultRecorder::class)->record($down, false, null, 'HTTP 503');
    Check::factory()->create(['enabled' => false]);

    $host = Host::factory()->enrolled()->create(['last_seen_at' => now()]);
    foreach (['cpu.usage_pct' => 20, 'mem.used_pct' => 50, 'disk.used_pct' => 70] as $key => $v) {
        MetricRollup::create(['host_id' => $host->id, 'metric_key' => $key, 'bucket_size' => 60, 'bucket_start' => now()->startOfMinute(), 'avg' => $v]);
    }

    $json = $this->getJson(route('api.wallboard', $token))->assertOk()->json();

    expect($json['summary'])->toMatchArray(['up' => 1, 'down' => 1, 'unknown' => 0, 'hosts_online' => 1, 'hosts_total' => 1])
        ->and($json['active_incidents'])->toHaveCount(1)
        ->and($json['checks_failing'][0]['error'])->toBe('HTTP 503')
        ->and($json['hosts'][0])->toMatchArray(['cpu_avg_5m' => 20.0, 'mem_pct' => 50.0, 'disk_pct' => 70.0, 'online' => true]);
});

it('filters by group, including sub-groups', function () {
    $group = Group::factory()->create();
    $sub = Group::factory()->create(['parent_id' => $group->id]);
    ['token' => $token] = Wallboard::createWithToken(['name' => 'C', 'group_id' => $group->id]);
    Check::factory()->create(['group_id' => $group->id]);
    Check::factory()->create(['group_id' => $sub->id]);
    Check::factory()->create();

    expect($this->getJson(route('api.wallboard', $token))->json('summary.unknown'))->toBe(2);
});

it('caches the payload and runs a bounded number of queries', function () {
    Cache::flush();
    ['token' => $token] = Wallboard::createWithToken(['name' => 'NOC']);
    Check::factory()->count(30)->create();
    Host::factory()->count(10)->create(['enrolled_at' => now()]);

    $this->getJson(route('api.wallboard', $token))->assertOk(); // warm (lookup + build)

    DB::enableQueryLog();
    $this->getJson(route('api.wallboard', $token))->assertOk();
    $queries = count(DB::getQueryLog());

    expect($queries)->toBeLessThanOrEqual(2); // token lookup only; payload from cache
});
