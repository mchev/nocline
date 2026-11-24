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
