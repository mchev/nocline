<?php

use App\Modules\Hosts\Models\Host;
use App\Modules\Metrics\Models\MetricRollup;
use App\Modules\Metrics\Services\HostMetricsReader;

it('builds cpu mem disk series from 1m rollups', function () {
    $host = Host::factory()->enrolled()->create();

    foreach (range(0, 4) as $i) {
        $start = now()->subMinutes(4 - $i)->startOfMinute();

        MetricRollup::create([
            'host_id' => $host->id,
            'metric_key' => 'cpu.usage_pct',
            'bucket_size' => MetricRollup::BUCKET_1M,
            'bucket_start' => $start,
            'avg' => 10 + $i,
            'sample_count' => 1,
        ]);
        MetricRollup::create([
            'host_id' => $host->id,
            'metric_key' => 'mem.used_pct',
            'bucket_size' => MetricRollup::BUCKET_1M,
            'bucket_start' => $start,
            'avg' => 40 + $i,
            'sample_count' => 1,
        ]);
        MetricRollup::create([
            'host_id' => $host->id,
            'metric_key' => 'disk.used_pct',
            'bucket_size' => MetricRollup::BUCKET_1M,
            'bucket_start' => $start,
            'avg' => 70,
            'sample_count' => 1,
        ]);
    }

    $series = app(HostMetricsReader::class)->forHosts([$host], 5);

    expect($series[$host->id]['cpu_now'])->toBe(14.0)
        ->and($series[$host->id]['mem_now'])->toBe(44.0)
        ->and($series[$host->id]['disk_now'])->toBe(70.0)
        ->and(array_filter($series[$host->id]['cpu'], fn ($v) => $v !== null))->toHaveCount(5);
});

it('accepts demo metric key aliases', function () {
    $host = Host::factory()->enrolled()->create();

    MetricRollup::create([
        'host_id' => $host->id,
        'metric_key' => 'cpu.usage',
        'bucket_size' => MetricRollup::BUCKET_1M,
        'bucket_start' => now()->startOfMinute(),
        'avg' => 33,
        'sample_count' => 1,
    ]);

    $series = app(HostMetricsReader::class)->forHosts([$host], 1);

    expect($series[$host->id]['cpu_now'])->toBe(33.0);
});

it('merges latest disk byte rollups into forHosts', function () {
    $host = Host::factory()->enrolled()->create();
    $start = now()->startOfMinute();

    MetricRollup::create([
        'host_id' => $host->id,
        'metric_key' => 'disk.used_pct',
        'bucket_size' => MetricRollup::BUCKET_1M,
        'bucket_start' => $start,
        'avg' => 2,
        'sample_count' => 1,
    ]);
    MetricRollup::create([
        'host_id' => $host->id,
        'metric_key' => 'disk.used_bytes',
        'bucket_size' => MetricRollup::BUCKET_1M,
        'bucket_start' => $start,
        'avg' => 5.2 * 1024 ** 3,
        'sample_count' => 1,
    ]);
    MetricRollup::create([
        'host_id' => $host->id,
        'metric_key' => 'disk.total_bytes',
        'bucket_size' => MetricRollup::BUCKET_1M,
        'bucket_start' => $start,
        'avg' => 250 * 1024 ** 3,
        'sample_count' => 1,
    ]);

    $series = app(HostMetricsReader::class)->forHosts([$host], 1);

    expect($series[$host->id]['disk_now'])->toBe(2.0)
        ->and($series[$host->id]['disk_used_bytes'])->toEqual(5.2 * 1024 ** 3)
        ->and($series[$host->id]['disk_total_bytes'])->toEqual(250.0 * 1024 ** 3);
});

it('builds a multi-hour chart from the right rollup bucket size', function () {
    $host = Host::factory()->enrolled()->create();
    $start = now()->subHours(2)->startOfMinute();

    foreach (range(0, 4) as $i) {
        $bucket = $start->copy()->addMinutes($i * 15);

        MetricRollup::create([
            'host_id' => $host->id,
            'metric_key' => 'cpu.usage_pct',
            'bucket_size' => MetricRollup::BUCKET_1M,
            'bucket_start' => $bucket,
            'avg' => 20 + $i,
            'sample_count' => 1,
        ]);
        MetricRollup::create([
            'host_id' => $host->id,
            'metric_key' => 'mem.used_pct',
            'bucket_size' => MetricRollup::BUCKET_1M,
            'bucket_start' => $bucket,
            'avg' => 50,
            'sample_count' => 1,
        ]);
    }

    $chart = app(HostMetricsReader::class)->chart($host, 24);

    expect($chart['hours'])->toBe(24)
        ->and($chart['bucket_seconds'])->toBe(MetricRollup::BUCKET_1M)
        ->and($chart['points'])->not->toBeEmpty();

    $withCpu = collect($chart['points'])->first(fn (array $p) => $p['cpu'] !== null);

    expect($withCpu)->not->toBeNull()
        ->and($withCpu['mem'])->toBe(50.0);
});

it('uses 5m rollups for a 7-day chart window', function () {
    $host = Host::factory()->enrolled()->create();
    $start = now()->subDays(1)->startOfMinute();
    $ts = intdiv((int) $start->timestamp, MetricRollup::BUCKET_5M) * MetricRollup::BUCKET_5M;

    MetricRollup::create([
        'host_id' => $host->id,
        'metric_key' => 'disk.used_pct',
        'bucket_size' => MetricRollup::BUCKET_5M,
        'bucket_start' => now()->setTimestamp($ts),
        'avg' => 88,
        'sample_count' => 1,
    ]);

    $chart = app(HostMetricsReader::class)->chart($host, 168);

    expect($chart['bucket_seconds'])->toBe(MetricRollup::BUCKET_5M);

    $disk = collect($chart['points'])->first(fn (array $p) => $p['disk'] === 88.0);

    expect($disk)->not->toBeNull();
});
