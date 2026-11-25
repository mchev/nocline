<?php

use App\Models\User;
use App\Modules\Hosts\Models\Host;
use App\Modules\Metrics\Models\MetricRollup;

it('creates a host and flashes a one-time enrollment token', function () {
    $this->actingAs(User::factory()->tech()->create())
        ->post(route('hosts.store'), ['name' => 'web-1'])
        ->assertSessionHas('enrollment.token');

    $host = Host::firstOrFail();
    expect($host->enrollment_token_hash)->not->toBeNull();
});

it('forbids viewers from creating hosts', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('hosts.store'), ['name' => 'web-1'])
        ->assertForbidden();
});

it('enrolls an agent once and rejects token reuse', function () {
    $host = Host::factory()->create();
    $token = $host->issueEnrollmentToken();

    $response = $this->postJson(route('api.enroll'), ['token' => $token, 'agent_version' => '0.1.0'])
        ->assertCreated();

    expect($host->refresh()->api_token_hash)->toBe(Host::hashToken($response->json('token')))
        ->and($host->enrollment_token_hash)->toBeNull()
        ->and($host->agent_version)->toBe('0.1.0');

    $this->postJson(route('api.enroll'), ['token' => $token])->assertUnprocessable();
});

it('rejects expired enrollment tokens', function () {
    $host = Host::factory()->create();
    $token = $host->issueEnrollmentToken();
    $this->travel(25)->hours();

    $this->postJson(route('api.enroll'), ['token' => $token])->assertUnprocessable();
});

it('requires a valid agent token to ingest', function () {
    $this->postJson(route('api.ingest'), [])->assertUnauthorized();
    $this->withToken('nope')->postJson(route('api.ingest'), [])->assertUnauthorized();
});

it('validates the ingest payload', function () {
    Host::factory()->enrolled()->create();

    $this->withToken('pha_test')->postJson(route('api.ingest'), ['metrics' => [['key' => 'BAD KEY']]])
        ->assertUnprocessable();
});

it('merges pushes into a single 1-minute rollup and marks the host seen', function () {
    $host = Host::factory()->enrolled()->create();
    $this->travelTo(now()->startOfMinute()->addSeconds(5));

    $push = fn (array $m) => $this->withToken('pha_test')->postJson(route('api.ingest'), [
        'agent_version' => '0.1.0',
        'metrics' => [['key' => 'cpu.usage_pct', ...$m]],
    ])->assertAccepted();

    $push(['min' => 2, 'max' => 40, 'avg' => 10, 'p95' => 35]);
    $this->travel(20)->seconds();
    $push(['min' => 1, 'max' => 20, 'avg' => 20, 'p95' => 50]);

    expect(MetricRollup::count())->toBe(1);

    $rollup = MetricRollup::first();
    expect($rollup->sample_count)->toBe(2)
        ->and($rollup->min)->toBe(1.0)
        ->and($rollup->max)->toBe(40.0)
        ->and($rollup->avg)->toBe(15.0)
        ->and($rollup->p95)->toBe(50.0)
        ->and($host->refresh()->isOnline())->toBeTrue();
});

it('prunes rollups beyond retention', function () {
    $host = Host::factory()->create();
    $row = fn (int $size, int $daysAgo) => MetricRollup::create([
        'host_id' => $host->id, 'metric_key' => 'cpu.usage_pct', 'bucket_size' => $size,
        'bucket_start' => now()->subDays($daysAgo), 'avg' => 1,
    ]);
    $row(60, 8);
    $row(60, 1);
    $row(300, 80);
    $row(300, 100);

    $this->artisan('nocline:metrics:prune')->assertSuccessful();

    expect(MetricRollup::count())->toBe(2);
});

it('lists hosts with online status', function () {
    Host::factory()->enrolled()->create(['last_seen_at' => now()]);
    Host::factory()->create();

    $this->actingAs(User::factory()->create())
        ->get(route('hosts.index'))
        ->assertInertia(fn ($page) => $page->component('hosts/Index')->has('hosts', 2));
});

it('rolls 1m buckets into weighted 5m and 1h buckets idempotently', function () {
    $host = Host::factory()->create();
    $this->travelTo(now()->startOfHour()->addHours(2)->addMinutes(7));
    $base = now()->startOfHour()->subMinutes(5); // last completed 5m bucket of the previous hour

    foreach ([0, 1] as $i) {
        MetricRollup::create([
            'host_id' => $host->id, 'metric_key' => 'cpu.usage_pct', 'bucket_size' => 60,
            'bucket_start' => $base->copy()->addMinutes($i), 'min' => 5 + $i, 'max' => 10 * ($i + 1),
            'avg' => $i === 0 ? 10 : 30, 'p95' => 9, 'sample_count' => $i === 0 ? 1 : 3,
        ]);
    }

    $this->artisan('nocline:metrics:rollup')->assertSuccessful();
    $this->artisan('nocline:metrics:rollup')->assertSuccessful();

    $five = MetricRollup::where('bucket_size', 300)->sole();
    $hour = MetricRollup::where('bucket_size', 3600)->sole();

    expect($five->avg)->toBe(25.0)
        ->and($five->min)->toBe(5.0)
        ->and($five->max)->toBe(20.0)
        ->and($five->sample_count)->toBe(4)
        ->and($hour->sample_count)->toBe(4)
        ->and($hour->avg)->toBe(25.0);
});
