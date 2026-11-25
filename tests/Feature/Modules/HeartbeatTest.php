<?php

use App\Models\User;
use App\Modules\Checks\Enums\CheckStatus;
use App\Modules\Checks\Enums\CheckType;
use App\Modules\Checks\Models\Check;
use App\Modules\Checks\Models\CheckEvent;
use App\Modules\Incidents\Models\Incident;
use Illuminate\Support\Facades\RateLimiter;

function heartbeat(array $attributes = [], int $interval = 60): Check
{
    return Check::factory()->heartbeat($interval)->create($attributes);
}

function status(Check $check): CheckStatus
{
    return $check->state()->first()->status;
}

it('gives every heartbeat check a unique random token and forces an immediate failure threshold', function () {
    $a = heartbeat();
    $b = heartbeat();

    expect($a->heartbeat_token)->toHaveLength(Check::HEARTBEAT_TOKEN_LENGTH)
        ->and($a->heartbeat_token)->not->toBe($b->heartbeat_token)
        ->and($a->failure_threshold)->toBe(1)
        ->and(Check::factory()->create()->heartbeat_token)->toBeNull();
});

it('records a success ping: unknown becomes up and the endpoint answers ok', function () {
    $check = heartbeat();

    $this->get("/api/v1/heartbeat/{$check->heartbeat_token}")->assertOk()->assertSee('ok', false);

    expect(status($check))->toBe(CheckStatus::Up)
        ->and($check->state()->first()->last_ping_at)->not->toBeNull()
        ->and(Incident::count())->toBe(0);
    $this->post("/api/v1/heartbeat/{$check->heartbeat_token}")->assertOk();
    expect(CheckEvent::count())->toBe(1);
});

it('records an explicit failure through the path or the query and opens one incident', function () {
    $check = heartbeat();
    $this->get("/api/v1/heartbeat/{$check->heartbeat_token}");

    $this->get("/api/v1/heartbeat/{$check->heartbeat_token}/fail?msg=".urlencode('backup: disk full'))->assertOk();

    expect(status($check))->toBe(CheckStatus::Down)
        ->and($check->state()->first()->last_error)->toBe('backup: disk full')
        ->and(Incident::open()->count())->toBe(1);

    $this->get("/api/v1/heartbeat/{$check->heartbeat_token}");
    expect(status($check))->toBe(CheckStatus::Up)->and(Incident::open()->count())->toBe(0);

    $this->post("/api/v1/heartbeat/{$check->heartbeat_token}", ['status' => 'fail']);
    expect(status($check))->toBe(CheckStatus::Down)
        ->and($check->state()->first()->last_error)->toBe('The job reported a failure');
});

it('truncates the failure message to 200 characters', function () {
    $check = heartbeat();

    $this->get("/api/v1/heartbeat/{$check->heartbeat_token}/fail?msg=".str_repeat('x', 500));

    expect(mb_strlen($check->state()->first()->last_error))->toBe(200);
});

it('answers an identical bare 404 for unknown, malformed or non-heartbeat tokens', function () {
    $http = Check::factory()->create(['heartbeat_token' => str_repeat('a', 40)]);
    heartbeat();

    $eventsBefore = CheckEvent::count();

    foreach ([str_repeat('b', 40), 'short', $http->heartbeat_token] as $token) {
        $this->get("/api/v1/heartbeat/{$token}")->assertNotFound()->assertExactJson(['message' => 'Not found.']);
        $this->get("/api/v1/heartbeat/{$token}/fail")->assertNotFound()->assertExactJson(['message' => 'Not found.']);
    }

    expect(CheckEvent::count())->toBe($eventsBefore);
});

it('ignores pings for a paused check without revealing it', function () {
    $check = heartbeat(['enabled' => false]);

    $this->get("/api/v1/heartbeat/{$check->heartbeat_token}")->assertOk();

    expect(status($check))->toBe(CheckStatus::Unknown);
});

it('throttles the public endpoint', function () {
    RateLimiter::clear('127.0.0.1');
    $check = heartbeat();

    for ($i = 0; $i < 120; $i++) {
        $this->get("/api/v1/heartbeat/{$check->heartbeat_token}")->assertOk();
    }

    $this->get("/api/v1/heartbeat/{$check->heartbeat_token}")->assertStatus(429);
});

it('keeps a never-pinged check unknown until created_at + interval + grace has passed', function () {
    $this->travelTo(now()->startOfMinute());
    $check = heartbeat(interval: 600);   // grace defaults to 60 s

    $this->travel(659)->seconds();
    $this->artisan('phare:heartbeats:check')->assertSuccessful();
    expect(status($check))->toBe(CheckStatus::Unknown);

    $this->travel(2)->seconds();
    $this->artisan('phare:heartbeats:check');
    expect(status($check))->toBe(CheckStatus::Down)
        ->and($check->state()->first()->last_error)->toBe('No ping received in time')
        ->and(Incident::open()->count())->toBe(1);
});

it('marks an overdue heartbeat down once, honours the grace period and recovers on the next ping', function () {
    $this->travelTo(now()->startOfMinute());
    $check = heartbeat(['config' => ['grace_sec' => 300]], interval: 3600);
    $this->get("/api/v1/heartbeat/{$check->heartbeat_token}");

    $this->travel(3600 + 299)->seconds();
    $this->artisan('phare:heartbeats:check');
    expect(status($check))->toBe(CheckStatus::Up);

    $this->travel(2)->seconds();
    $this->artisan('phare:heartbeats:check');
    $this->travel(60)->seconds();
    $this->artisan('phare:heartbeats:check');
    $this->travel(60)->seconds();
    $this->artisan('phare:heartbeats:check');

    expect(status($check))->toBe(CheckStatus::Down)
        ->and(CheckEvent::count())->toBe(2)
        ->and(Incident::open()->count())->toBe(1);

    $this->get("/api/v1/heartbeat/{$check->heartbeat_token}");
    expect(status($check))->toBe(CheckStatus::Up);
    $this->artisan('phare:heartbeats:check');
    expect(status($check))->toBe(CheckStatus::Up)->and(CheckEvent::count())->toBe(3);
});

it('does not flap: a ping each interval keeps it up with a single event', function () {
    $this->travelTo(now()->startOfMinute());
    $check = heartbeat();

    for ($i = 0; $i < 10; $i++) {
        $this->get("/api/v1/heartbeat/{$check->heartbeat_token}");
        $this->travel(61)->seconds();
        $this->artisan('phare:heartbeats:check');
    }

    expect(status($check))->toBe(CheckStatus::Up)->and(CheckEvent::count())->toBe(1);
});

it('does not evaluate paused checks or other check types', function () {
    $this->travelTo(now()->startOfMinute());
    $paused = heartbeat(['enabled' => false]);
    $http = Check::factory()->create();
    $this->travel(1)->days();

    $this->artisan('phare:heartbeats:check')->assertSuccessful();

    expect(status($paused))->toBe(CheckStatus::Unknown)->and(status($http))->toBe(CheckStatus::Unknown);
});

it('is scheduled every minute', function () {
    $this->artisan('schedule:list')->expectsOutputToContain('phare:heartbeats:check')->assertSuccessful();
});

it('validates heartbeat checks through the web form and generates the token', function () {
    $this->actingAs(User::factory()->tech()->create());

    $this->post(route('checks.store'), ['name' => 'Backup', 'type' => 'heartbeat', 'interval_sec' => 604800, 'config' => ['grace_sec' => 3600]])
        ->assertSessionHasNoErrors();

    $check = Check::first();
    expect($check->type)->toBe(CheckType::Heartbeat)->and($check->heartbeat_token)->not->toBeNull()
        ->and($check->heartbeatGraceSeconds())->toBe(3600);

    $this->post(route('checks.store'), ['name' => 'Site', 'type' => 'http', 'target' => 'https://a.test', 'interval_sec' => 604800])
        ->assertSessionHasErrors('interval_sec');
});

it('computes a default grace of 10% of the interval with a 60 s floor', function () {
    expect(heartbeat(interval: 60)->heartbeatGraceSeconds())->toBe(60)
        ->and(heartbeat(interval: 86400)->heartbeatGraceSeconds())->toBe(8640);
});

it('adds a scheduled task from the guided wizard and shows the ping url and snippets page', function () {
    $this->actingAs(User::factory()->tech()->create());

    $response = $this->post(route('monitor.heartbeat.store'), ['name' => 'Nightly backup', 'interval_sec' => 86400, 'group_name' => 'Ops']);

    $check = Check::first();
    $response->assertRedirect(route('monitor.heartbeat', $check));
    expect($check->type)->toBe(CheckType::Heartbeat)->and($check->group->name)->toBe('Ops');

    $this->get(route('monitor.heartbeat', $check))->assertInertia(fn ($page) => $page->component('monitor/Heartbeat')
        ->where('check.name', 'Nightly backup')
        ->where('check.pinged', false)
        ->where('url', url("/api/v1/heartbeat/{$check->heartbeat_token}")));

    $this->get("/api/v1/heartbeat/{$check->heartbeat_token}");
    $this->get(route('monitor.heartbeat', $check))->assertInertia(fn ($page) => $page->where('check.pinged', true)->where('check.status', 'up'));
});

it('rejects invalid wizard input and protects the secret page', function () {
    $this->actingAs(User::factory()->tech()->create());
    $this->post(route('monitor.heartbeat.store'), ['name' => '', 'interval_sec' => 5])->assertSessionHasErrors(['name', 'interval_sec']);

    $check = heartbeat();
    $http = Check::factory()->create();
    $this->get(route('monitor.heartbeat', $http))->assertNotFound();

    $this->actingAs(User::factory()->create());
    $this->get(route('monitor.heartbeat', $check))->assertForbidden();
    $this->post(route('monitor.heartbeat.store'), ['name' => 'x', 'interval_sec' => 60])->assertForbidden();
});

it('never serialises the heartbeat token on the model', function () {
    expect(heartbeat()->toArray())->not->toHaveKey('heartbeat_token');
});
