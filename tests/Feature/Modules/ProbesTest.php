<?php

use App\Models\User;
use App\Modules\Checks\Enums\CheckExecution;
use App\Modules\Checks\Enums\CheckStatus;
use App\Modules\Checks\Enums\CheckType;
use App\Modules\Checks\Models\Check;
use App\Modules\Incidents\Models\Incident;
use App\Modules\Probes\Models\Probe;

function makeProbe(string $name, string $token): Probe
{
    $probe = Probe::create(['name' => $name, 'role' => 'external']);
    $probe->forceFill(['api_token_hash' => Probe::hashToken($token), 'enrolled_at' => now()])->save();

    return $probe;
}

function pushResult($test, string $token, Check $check, bool $ok, ?string $error = null)
{
    return $test->withToken($token)->postJson(route('api.probe.results'), [
        'results' => [['check_id' => $check->id, 'ok' => $ok, 'latency_ms' => $ok ? 50 : null, 'error' => $error]],
    ])->assertAccepted();
}

it('enrolls a probe through the shared enroll endpoint', function () {
    $probe = Probe::create(['name' => 'eu-1', 'role' => 'external']);
    $token = $probe->issueEnrollmentToken();

    $response = $this->postJson(route('api.enroll'), ['token' => $token])->assertCreated();

    expect($response->json('role'))->toBe('probe')
        ->and($probe->refresh()->api_token_hash)->toBe(Probe::hashToken($response->json('token')));
});

it('requires a probe token and ignores host tokens', function () {
    $this->getJson(route('api.probe.work'))->assertUnauthorized();
    $this->withToken('pha_test')->getJson(route('api.probe.work'))->assertUnauthorized();
});

it('leases due probe checks once per interval and filters by capability', function () {
    makeProbe('eu-1', 'prp_a');
    $icmp = Check::factory()->create(['type' => CheckType::Icmp, 'target' => '1.1.1.1']);
    $http = Check::factory()->create(['execution' => CheckExecution::Probe]);
    Check::factory()->create(); // hub executed

    $work = fn (string $caps) => $this->withToken('prp_a')->getJson(route('api.probe.work', ['capabilities' => $caps]))
        ->assertOk()->json('checks');

    expect(collect($work('http,tcp'))->pluck('id')->all())->toBe([$http->id])
        ->and($work('http,tcp'))->toBe([]);

    expect(collect($work('icmp'))->pluck('id')->all())->toBe([$icmp->id]);
});

it('does not run probe checks from the hub scheduler', function () {
    $check = Check::factory()->create(['execution' => CheckExecution::Probe]);

    $this->artisan('nocline:checks:run-due')->assertSuccessful();

    expect($check->state()->first()->status)->toBe(CheckStatus::Unknown);
});

it('applies majority quorum across probes', function () {
    makeProbe('a', 'prp_a');
    makeProbe('b', 'prp_b');
    makeProbe('c', 'prp_c');
    $check = Check::factory()->create(['execution' => CheckExecution::Probe, 'quorum' => 'majority', 'failure_threshold' => 1]);

    pushResult($this, 'prp_a', $check, true);
    $this->travel(60)->seconds();
    pushResult($this, 'prp_b', $check, false, 'timeout');
    pushResult($this, 'prp_c', $check, true);
    expect($check->state()->first()->status)->toBe(CheckStatus::Up);

    $this->travel(60)->seconds();
    pushResult($this, 'prp_c', $check, false, 'timeout');
    expect($check->state()->first()->status)->toBe(CheckStatus::Down)
        ->and(Incident::open()->count())->toBe(1);
});

it('applies any and all quorum', function () {
    makeProbe('a', 'prp_a');
    makeProbe('b', 'prp_b');
    $any = Check::factory()->create(['execution' => CheckExecution::Probe, 'quorum' => 'any', 'failure_threshold' => 1]);
    $all = Check::factory()->create(['execution' => CheckExecution::Probe, 'quorum' => 'all', 'failure_threshold' => 1]);

    foreach ([$any, $all] as $check) {
        pushResult($this, 'prp_a', $check, true);
        pushResult($this, 'prp_b', $check, false, 'refused');
    }

    expect($any->state()->first()->status)->toBe(CheckStatus::Down)
        ->and($all->state()->first()->status)->toBe(CheckStatus::Up);
});

it('ignores stale or disabled probes in quorum', function () {
    $a = makeProbe('a', 'prp_a');
    $b = makeProbe('b', 'prp_b');
    $check = Check::factory()->create(['execution' => CheckExecution::Probe, 'quorum' => 'any', 'failure_threshold' => 1]);

    pushResult($this, 'prp_b', $check, false, 'down');
    $b->update(['enabled' => false]);
    $this->travel(60)->seconds();
    pushResult($this, 'prp_a', $check, true);

    expect($check->state()->first()->status)->toBe(CheckStatus::Up);
});

it('lets admins manage probes and shows a one-time token', function () {
    $this->actingAs(User::factory()->tech()->create())
        ->post(route('probes.store'), ['name' => 'x', 'role' => 'external'])->assertForbidden();

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('probes.store'), ['name' => 'eu-1', 'role' => 'external'])
        ->assertSessionHas('enrollment.token');

    $this->get(route('probes.index'))->assertInertia(fn ($p) => $p->component('probes/Index')->has('probes', 1));
});
