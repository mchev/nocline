<?php

use App\Models\User;
use App\Modules\Checks\Enums\CheckType;
use App\Modules\Checks\Models\Check;
use App\Modules\Hosts\Models\Host;
use App\Modules\Metrics\Models\MetricRollup;
use Inertia\Testing\AssertableInertia as Assert;

it('shows host detail with linked monitors', function () {
    $user = User::factory()->create();
    $host = Host::factory()->create(['name' => 'web-01', 'color' => '#336699']);
    $agent = Check::factory()->create([
        'type' => CheckType::Agent,
        'host_id' => $host->id,
        'name' => $host->name,
        'interval_sec' => 120,
    ]);
    $site = Check::factory()->create([
        'name' => 'Blog',
        'type' => CheckType::Http,
        'host_id' => $host->id,
        'target' => 'https://blog.test',
    ]);

    $this->actingAs($user)
        ->get(route('hosts.show', $host))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('hosts/Show')
            ->where('host.id', $host->id)
            ->where('host.color', '#336699')
            ->where('host.agent_check.interval_sec', 120)
            ->has('linked_checks', 1)
            ->where('linked_checks.0.id', $site->id)
            ->has('metrics_chart')
            ->where('metrics_chart.hours', 24)
            ->has('metrics_chart.points')
            ->has('recent_incidents'));
});

it('returns metrics chart json for a viewer', function () {
    $user = User::factory()->create();
    $host = Host::factory()->enrolled()->create();

    MetricRollup::create([
        'host_id' => $host->id,
        'metric_key' => 'cpu.usage_pct',
        'bucket_size' => MetricRollup::BUCKET_1M,
        'bucket_start' => now()->startOfMinute(),
        'avg' => 42,
        'sample_count' => 1,
    ]);

    $this->actingAs($user)
        ->getJson(route('hosts.metrics-chart', ['host' => $host, 'hours' => 24]))
        ->assertOk()
        ->assertJsonPath('hours', 24)
        ->assertJsonStructure(['bucket_seconds', 'end_at', 'points']);
});

it('updates host color and agent check interval together', function () {
    $user = User::factory()->tech()->create();
    $host = Host::factory()->create();
    Check::factory()->create([
        'type' => CheckType::Agent,
        'host_id' => $host->id,
        'interval_sec' => 60,
    ]);

    $this->actingAs($user)
        ->patch(route('hosts.update', $host), [
            'name' => $host->name,
            'color' => '#ff0000',
            'interval_sec' => 90,
            'timeout_sec' => 15,
            'failure_threshold' => 3,
        ])
        ->assertRedirect();

    $host->refresh();
    expect($host->color)->toBe('#ff0000');
    expect($host->agentCheck->interval_sec)->toBe(90)
        ->and($host->agentCheck->timeout_sec)->toBe(15)
        ->and($host->agentCheck->failure_threshold)->toBe(3);
});

it('links and unlinks a non-agent check to a host', function () {
    $user = User::factory()->tech()->create();
    $host = Host::factory()->create();
    $check = Check::factory()->create(['type' => CheckType::Http, 'host_id' => null]);

    $this->actingAs($user)
        ->post(route('hosts.attach-check', $host), ['check_id' => $check->id])
        ->assertRedirect();

    expect($check->refresh()->host_id)->toBe($host->id);

    $this->actingAs($user)
        ->delete(route('hosts.detach-check', [$host, $check]))
        ->assertRedirect();

    expect($check->refresh()->host_id)->toBeNull();
});
