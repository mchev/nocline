<?php

use App\Models\User;
use App\Modules\Checks\Enums\CheckStatus;
use App\Modules\Checks\Enums\CheckType;
use App\Modules\Checks\Models\Check;
use App\Modules\Hosts\Models\Host;
use App\Modules\Hosts\Support\HostMetricAlerts;
use App\Modules\Metrics\Models\MetricRollup;
use App\Modules\Metrics\Services\HostMetricAlertEvaluator;

it('opens a resource check incident when cpu stays above the threshold', function () {
    $host = Host::factory()->enrolled()->create();
    Check::create([
        'name' => $host->name.' — load',
        'type' => CheckType::HostResources,
        'host_id' => $host->id,
        'target' => '',
        'interval_sec' => 60,
        'timeout_sec' => 10,
        'failure_threshold' => 1,
    ]);

    $at = now()->startOfMinute();

    for ($i = 4; $i >= 0; $i--) {
        MetricRollup::query()->create([
            'host_id' => $host->id,
            'metric_key' => 'cpu.usage_pct',
            'bucket_size' => MetricRollup::BUCKET_1M,
            'bucket_start' => $at->copy()->subMinutes($i),
            'min' => 95,
            'max' => 96,
            'avg' => 95,
            'p95' => 96,
            'sample_count' => 1,
        ]);
    }

    app(HostMetricAlertEvaluator::class)->evaluate($host);

    $check = Check::query()->where('host_id', $host->id)->where('type', CheckType::HostResources)->firstOrFail();
    expect($check->state?->status)->toBe(CheckStatus::Down);
});

it('stores custom metric alert thresholds on the host', function () {
    $user = User::factory()->tech()->create();
    $host = Host::factory()->enrolled()->create();

    $this->actingAs($user)
        ->patch(route('hosts.update', $host), [
            'name' => $host->name,
            'metric_alerts' => [
                'enabled' => true,
                'cpu_pct' => 80,
                'mem_pct' => 85,
                'disk_pct' => 88,
                'load_1m' => null,
                'sustain_minutes' => 3,
            ],
        ])
        ->assertRedirect();

    expect(HostMetricAlerts::resolve($host->refresh()->metric_alerts))->toMatchArray([
        'cpu_pct' => 80,
        'sustain_minutes' => 3,
    ]);
});
