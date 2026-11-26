<?php

use App\Models\User;
use App\Modules\Checks\Enums\CheckCategory;
use App\Modules\Checks\Models\Check;
use App\Modules\Checks\Services\CheckResultRecorder;
use App\Modules\Groups\Models\Group;
use App\Modules\Hosts\Models\Host;
use App\Modules\Metrics\Models\MetricRollup;
use App\Modules\Wallboard\Models\Wallboard;
use App\Modules\Wallboard\Services\WallboardAggregator;
use App\Modules\Wallboard\Support\HubRevision;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

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

it('exposes hub revision on the kiosk page for deploy reload', function () {
    ['token' => $token] = Wallboard::createWithToken(['name' => 'NOC TV']);
    $revision = app(HubRevision::class)->current();

    $this->get(route('wallboard.show', $token))
        ->assertInertia(fn ($p) => $p->component('wallboard/Show')
            ->where('name', 'NOC TV')
            ->where('hubRevision', $revision));
});

it('exposes public favicon urls and serves them without authentication', function () {
    Storage::fake('local');
    ['token' => $token] = Wallboard::createWithToken(['name' => 'NOC']);
    $site = Check::factory()->create([
        'category' => CheckCategory::Website,
        'name' => 'Shop',
        'favicon_path' => 'check-favicons/99.png',
    ]);
    $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
    Storage::disk('local')->put('check-favicons/99.png', $png);

    $siteRow = collect($this->getJson(route('api.wallboard', $token))->json('sites'))
        ->firstWhere('name', 'Shop');

    expect($siteRow['favicon_url'])->toContain('/wallboard/')
        ->and($siteRow['favicon_url'])->toContain('/favicon/');

    $response = $this->get($siteRow['favicon_url'])->assertOk();

    expect($response->headers->get('content-type'))->toStartWith('image/');

    $this->get(route('wallboard.favicon', ['token' => $token, 'check' => Check::factory()->create()->id]))
        ->assertNotFound();
});

it('aggregates summary, incidents, sites and host metrics', function () {
    ['token' => $token] = Wallboard::createWithToken(['name' => 'NOC']);
    $up = Check::factory()->create();
    $site = Check::factory()->create(['category' => CheckCategory::Website, 'name' => 'Shop']);
    $down = Check::factory()->create(['failure_threshold' => 1]);
    app(CheckResultRecorder::class)->record($up, true, 40);
    app(CheckResultRecorder::class)->record($site, true, 120);
    app(CheckResultRecorder::class)->record($down, false, null, 'HTTP 503');
    Check::factory()->create(['enabled' => false]);

    $host = Host::factory()->enrolled()->create(['last_seen_at' => now()]);
    foreach (['cpu.usage_pct' => 20, 'mem.used_pct' => 50, 'disk.used_pct' => 70] as $key => $v) {
        MetricRollup::create(['host_id' => $host->id, 'metric_key' => $key, 'bucket_size' => 60, 'bucket_start' => now()->startOfMinute(), 'avg' => $v]);
    }
    MetricRollup::create(['host_id' => $host->id, 'metric_key' => 'disk.used_bytes', 'bucket_size' => 60, 'bucket_start' => now()->startOfMinute(), 'avg' => 5.2 * 1024 ** 3]);
    MetricRollup::create(['host_id' => $host->id, 'metric_key' => 'disk.total_bytes', 'bucket_size' => 60, 'bucket_start' => now()->startOfMinute(), 'avg' => 250 * 1024 ** 3]);

    $json = $this->getJson(route('api.wallboard', $token))->assertOk()->json();

    expect($json)->toHaveKey('hub_revision')
        ->and($json['hub_revision'])->toBe(app(HubRevision::class)->current());

    expect($json['summary'])->toMatchArray(['down' => 1, 'maintenance' => 0])
        ->and($json)->not->toHaveKey('checks_failing')
        ->and($json['active_incidents'])->toHaveCount(1)
        ->and($json['hosts'][0]['online'])->toBeTrue()
        ->and($json['hosts'][0]['metrics'])->toMatchArray([
            'cpu_now' => 20.0,
            'mem_now' => 50.0,
            'disk_now' => 70.0,
            'disk_used_bytes' => 5.2 * 1024 ** 3,
            'disk_total_bytes' => 250 * 1024 ** 3,
        ])
        ->and(collect($json['sites'])->pluck('name'))->toContain('Shop')
        ->and(collect($json['sites'])->firstWhere('name', 'Shop')['latency_ms'])->toBe(120);
});

it('filters by group, including sub-groups', function () {
    $group = Group::factory()->create();
    $sub = Group::factory()->create(['parent_id' => $group->id]);
    ['token' => $token] = Wallboard::createWithToken(['name' => 'C', 'group_id' => $group->id]);
    Check::factory()->create(['group_id' => $group->id]);
    Check::factory()->create(['group_id' => $sub->id]);
    Check::factory()->create();

    $json = $this->getJson(route('api.wallboard', $token))->assertOk()->json();

    expect(collect($json['sites'] ?? []))->toHaveCount(2);
});

it('caches the wallboard payload between aggregator calls', function () {
    $this->freezeTime();

    Cache::flush();
    ['wallboard' => $wallboard] = Wallboard::createWithToken(['name' => 'NOC']);
    Check::factory()->count(5)->create();
    Host::factory()->count(3)->create(['enrolled_at' => now()]);

    $aggregator = app(WallboardAggregator::class);
    $cacheKey = "wallboard:{$wallboard->id}";

    $token = 'wb_cache_test_token';
    $first = $aggregator->payload($wallboard, $token);
    expect(Cache::has($cacheKey))->toBeTrue();

    Check::factory()->create(['name' => 'Would skew a live rebuild']);

    DB::enableQueryLog();
    DB::flushQueryLog();
    $second = $aggregator->payload($wallboard, $token);

    expect(count(DB::getQueryLog()))->toBe(0)
        ->and(collect($second['sites'])->pluck('id')->all())
        ->toEqual(collect($first['sites'])->pluck('id')->all());
});
