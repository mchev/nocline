<?php

use App\Models\User;
use App\Modules\Checks\Enums\CheckCategory;
use App\Modules\Checks\Enums\CheckStatus;
use App\Modules\Checks\Enums\CheckType;
use App\Modules\Checks\Models\Check;
use App\Modules\Hosts\Models\Host;
use App\Modules\Maintenance\Models\MaintenanceWindow;
use App\Modules\Metrics\Models\MetricRollup;
use Inertia\Testing\AssertableInertia as Assert;

it('exposes a live overview with sites servers and heartbeats', function () {
    $user = User::factory()->create();
    $host = Host::factory()->enrolled()->create(['name' => 'edge-1', 'last_seen_at' => now()]);

    MetricRollup::create([
        'host_id' => $host->id,
        'metric_key' => 'cpu.usage_pct',
        'bucket_size' => MetricRollup::BUCKET_1M,
        'bucket_start' => now()->startOfMinute(),
        'avg' => 22,
        'sample_count' => 1,
    ]);

    $site = Check::factory()->create([
        'name' => 'Acme site',
        'category' => CheckCategory::Website,
        'type' => CheckType::Http,
        'target' => 'https://acme.test',
    ]);
    $site->state()->update([
        'status' => CheckStatus::Up,
        'last_latency_ms' => 120,
    ]);

    $hb = Check::factory()->heartbeat()->create([
        'name' => 'Nightly job',
        'category' => CheckCategory::Other,
    ]);
    $hb->state()->update([
        'status' => CheckStatus::Up,
        'since_at' => now()->subMinutes(2),
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->has('overview')
            ->has('overview.sites', 1)
            ->has('overview.servers', 1)
            ->has('overview.heartbeats', 1)
            ->where('overview.servers.0.name', 'edge-1')
            ->where('overview.servers.0.online', true)
            ->where('overview.servers.0.metrics.cpu_now', 22)
            ->where('overview.sites.0.name', 'Acme site')
            ->where('overview.heartbeats.0.name', 'Nightly job'));
});

it('includes object storage monitors in overview and excludes them from heartbeats', function () {
    $user = User::factory()->create();

    $s3 = Check::factory()->create([
        'name' => 'OVH backup',
        'category' => CheckCategory::Other,
        'type' => CheckType::Http,
        'target' => 'https://s3.example.test/health.txt',
        'tags' => ['object-storage', 'ovh', 's3-bucket'],
    ]);
    $s3->state()->update([
        'status' => CheckStatus::Up,
        'last_latency_ms' => 88,
    ]);

    $hb = Check::factory()->heartbeat()->create([
        'name' => 'Cron sync',
        'category' => CheckCategory::Other,
    ]);
    $hb->state()->update(['status' => CheckStatus::Up]);

    $backupHb = Check::factory()->heartbeat()->create([
        'name' => 'S3 backup ping',
        'category' => CheckCategory::Other,
        'tags' => ['object-storage', 'hetzner', 's3-backup'],
    ]);
    $backupHb->state()->update(['status' => CheckStatus::Up]);

    $response = $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('overview.object_storage', 2)
            ->has('overview.heartbeats', 1)
            ->where('overview.heartbeats.0.name', 'Cron sync'));

    $objectStorage = collect($response->viewData('page')['props']['overview']['object_storage']);
    expect($objectStorage->pluck('name')->all())->toEqualCanonicalizing(['OVH backup', 'S3 backup ping']);
    expect($objectStorage->firstWhere('name', 'OVH backup')['latency_ms'])->toBe(88);
});

it('orders overview sections with problems first', function () {
    $user = User::factory()->create();

    $up = Check::factory()->create([
        'name' => 'Zeta up',
        'category' => CheckCategory::Website,
        'type' => CheckType::Http,
    ]);
    $up->state()->update(['status' => CheckStatus::Up]);

    $down = Check::factory()->create([
        'name' => 'Alpha down',
        'category' => CheckCategory::Website,
        'type' => CheckType::Http,
    ]);
    $down->state()->update(['status' => CheckStatus::Down]);

    $online = Host::factory()->enrolled('pha_online')->create(['name' => 'online-box', 'last_seen_at' => now()]);
    $offline = Host::factory()->enrolled('pha_offline')->create([
        'name' => 'offline-box',
        'last_seen_at' => now()->subMinutes(10),
    ]);

    MetricRollup::create([
        'host_id' => $online->id,
        'metric_key' => 'cpu.usage_pct',
        'bucket_size' => MetricRollup::BUCKET_1M,
        'bucket_start' => now()->startOfMinute(),
        'avg' => 10,
        'sample_count' => 1,
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('overview.sites.0.name', 'Alpha down')
            ->where('overview.sites.1.name', 'Zeta up')
            ->where('overview.servers.0.name', 'offline-box')
            ->where('overview.servers.1.name', 'online-box')
            ->where('overview.servers.0.online', false)
            ->where('overview.servers.1.online', true));
});

it('lists down sites before up sites even when down is in maintenance', function () {
    $user = User::factory()->create();

    $up = Check::factory()->create([
        'name' => 'AAA up',
        'category' => CheckCategory::Website,
        'type' => CheckType::Http,
    ]);
    $up->state()->update(['status' => CheckStatus::Up]);

    $downMaint = Check::factory()->create([
        'name' => 'ZZZ down maintenance',
        'category' => CheckCategory::Website,
        'type' => CheckType::Http,
    ]);
    $downMaint->state()->update(['status' => CheckStatus::Down]);

    MaintenanceWindow::factory()->create([
        'check_id' => $downMaint->id,
        'starts_at' => now()->subHour(),
        'ends_at' => now()->addHour(),
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('overview.sites.0.name', 'ZZZ down maintenance')
            ->where('overview.sites.1.name', 'AAA up'));
});

it('includes metric sparklines on the servers page', function () {
    $user = User::factory()->create();
    $host = Host::factory()->enrolled()->create(['last_seen_at' => now()]);

    MetricRollup::create([
        'host_id' => $host->id,
        'metric_key' => 'mem.used_pct',
        'bucket_size' => MetricRollup::BUCKET_1M,
        'bucket_start' => now()->startOfMinute(),
        'avg' => 55,
        'sample_count' => 1,
    ]);

    $this->actingAs($user)
        ->get(route('hosts.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('hosts/Index')
            ->has('hosts', 1)
            ->where('hosts.0.metrics.mem_now', 55)
            ->where('hosts.0.online', true));
});
