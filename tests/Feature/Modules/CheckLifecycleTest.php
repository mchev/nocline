<?php

use App\Models\User;
use App\Modules\Alertes\Models\AlertDelivery;
use App\Modules\Alertes\Models\NotificationChannel;
use App\Modules\Alertes\Notifications\IncidentMail;
use App\Modules\Checks\Enums\CheckStatus;
use App\Modules\Checks\Enums\CheckType;
use App\Modules\Checks\Models\Check;
use App\Modules\Checks\Models\CheckEvent;
use App\Modules\Checks\Services\CheckResultRecorder;
use App\Modules\Hosts\Models\Host;
use App\Modules\Incidents\Models\Incident;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

function recorder(): CheckResultRecorder
{
    return app(CheckResultRecorder::class);
}

it('creates a state row for a new check', function () {
    $check = Check::factory()->create();

    expect($check->state)->not->toBeNull()
        ->and($check->state->status)->toBe(CheckStatus::Unknown);
});

it('goes up from unknown with a single event and no incident', function () {
    $check = Check::factory()->create();

    recorder()->record($check, true, 120);
    recorder()->record($check, true, 110);

    expect(CheckEvent::count())->toBe(1)
        ->and($check->state()->first()->status)->toBe(CheckStatus::Up)
        ->and($check->state()->first()->last_latency_ms)->toBe(110)
        ->and(Incident::count())->toBe(0);
});

it('requires consecutive failures before going down and opens one incident', function () {
    $check = Check::factory()->create(['failure_threshold' => 2]);
    recorder()->record($check, true, 100);

    recorder()->record($check, false, null, 'timeout');
    expect($check->state()->first()->status)->toBe(CheckStatus::Up)
        ->and(Incident::count())->toBe(0);

    recorder()->record($check, false, null, 'timeout');
    recorder()->record($check, false, null, 'timeout');

    expect($check->state()->first()->status)->toBe(CheckStatus::Down)
        ->and(Incident::open()->count())->toBe(1)
        ->and(CheckEvent::count())->toBe(2);
});

it('resets the failure streak on success', function () {
    $check = Check::factory()->create(['failure_threshold' => 2]);
    recorder()->record($check, true);
    recorder()->record($check, false, null, 'x');
    recorder()->record($check, true);
    recorder()->record($check, false, null, 'x');

    expect($check->state()->first()->status)->toBe(CheckStatus::Up);
});

it('resolves the incident on recovery', function () {
    $check = Check::factory()->create(['failure_threshold' => 1]);
    recorder()->record($check, false, null, 'down');
    recorder()->record($check, true, 90);

    $incident = Incident::firstOrFail();
    expect($incident->isOpen())->toBeFalse()
        ->and($check->state()->first()->status)->toBe(CheckStatus::Up);
});

it('sends alerts once per incident and kind across channels', function () {
    Mail::fake();
    Http::fake();
    NotificationChannel::create(['name' => 'Ops', 'type' => 'email', 'config' => ['to' => 'ops@example.com']]);
    NotificationChannel::create(['name' => 'Hook', 'type' => 'webhook', 'config' => ['url' => 'https://hooks.test/x']]);

    $check = Check::factory()->create(['failure_threshold' => 1]);
    recorder()->record($check, false, null, 'boom');
    recorder()->record($check, false, null, 'boom');
    recorder()->record($check, true);

    Mail::assertSent(IncidentMail::class, 2);
    expect(Http::recorded(fn ($request) => str_contains($request->url(), 'hooks.test')))->toHaveCount(2);
    expect(AlertDelivery::where('status', 'sent')->count())->toBe(4);
});

it('records a failed delivery without breaking the flow', function () {
    Http::fake(['*' => Http::response('', 500)]);
    NotificationChannel::create(['name' => 'Hook', 'type' => 'webhook', 'config' => ['url' => 'https://hooks.test/x']]);

    $check = Check::factory()->create(['failure_threshold' => 1]);
    recorder()->record($check, false, null, 'boom');

    expect(AlertDelivery::first()->status)->toBe('failed')
        ->and(Incident::count())->toBe(1);
});

it('acknowledges an open incident as a tech but not as a viewer', function () {
    $incident = Incident::factory()->create([
        'resolved_at' => null,
        'acknowledged_at' => null,
        'acknowledged_by' => null,
    ]);

    $this->actingAs(User::factory()->create())
        ->post(route('incidents.acknowledge', $incident))->assertForbidden();

    $tech = User::factory()->tech()->create();

    $this->actingAs($tech)
        ->post(route('incidents.acknowledge', $incident))
        ->assertRedirect();

    $incident->refresh();

    expect($incident->acknowledged_by)->toBe($tech->id)
        ->and($incident->acknowledged_at)->not->toBeNull();
});

it('runs due http checks through the scheduler command', function () {
    Http::fake(['https://ok.test' => Http::response('hello', 200), 'https://ko.test' => Http::response('', 503)]);
    $ok = Check::factory()->create(['target' => 'https://ok.test']);
    $ko = Check::factory()->create(['target' => 'https://ko.test', 'failure_threshold' => 1]);
    $icmp = Check::factory()->create(['type' => CheckType::Icmp, 'target' => '1.1.1.1']);

    $this->artisan('phare:checks:run-due')->assertSuccessful();

    expect($ok->state()->first()->status)->toBe(CheckStatus::Up)
        ->and($ko->state()->first()->status)->toBe(CheckStatus::Down)
        ->and($icmp->state()->first()->status)->toBe(CheckStatus::Unknown);

    $this->artisan('phare:checks:run-due')->assertSuccessful();
    expect(CheckEvent::count())->toBe(2);
});

it('supports keyword matching and agent heartbeat checks', function () {
    Http::fake(['https://kw.test' => Http::response('welcome', 200)]);
    $kw = Check::factory()->create(['target' => 'https://kw.test', 'config' => ['keyword' => 'missing'], 'failure_threshold' => 1]);
    $host = Host::factory()->enrolled()->create(['last_seen_at' => now()]);
    $agent = Check::factory()->create(['type' => CheckType::Agent, 'host_id' => $host->id, 'target' => '']);

    $this->artisan('phare:checks:run-due')->assertSuccessful();

    expect($kw->state()->first()->status)->toBe(CheckStatus::Down)
        ->and($agent->state()->first()->status)->toBe(CheckStatus::Up);
});

it('lets techs create checks and validates host requirement for agent checks', function () {
    $this->actingAs(User::factory()->tech()->create());

    $this->post(route('checks.store'), ['name' => 'Site', 'type' => 'http', 'target' => 'https://example.com'])
        ->assertRedirect();
    $this->post(route('checks.store'), ['name' => 'Agent', 'type' => 'agent'])
        ->assertSessionHasErrors('host_id');

    expect(Check::count())->toBe(1);
});

it('restricts channel management to admins', function () {
    $payload = ['name' => 'Ops', 'type' => 'email', 'to' => 'ops@example.com'];

    $this->actingAs(User::factory()->tech()->create())
        ->post(route('alertes.channels.store'), $payload)->assertForbidden();

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('alertes.channels.store'), $payload)->assertRedirect();

    expect(NotificationChannel::first()->config)->toBe(['to' => 'ops@example.com']);
});

it('renders the checks, incidents and alert channel pages', function () {
    Incident::factory()->create();
    $this->actingAs(User::factory()->admin()->create());

    $this->get(route('checks.index'))->assertInertia(fn ($p) => $p->component('checks/Index'));
    $this->get(route('incidents.index'))->assertInertia(fn ($p) => $p->component('incidents/Index')->has('incidents.data', 1));
    $this->get(route('alertes.channels.index'))->assertInertia(fn ($p) => $p->component('alertes/Index'));
});

it('prunes old check events and alert deliveries only', function () {
    $check = Check::factory()->create();
    CheckEvent::create(['check_id' => $check->id, 'from_status' => 'unknown', 'to_status' => 'up', 'at' => now()->subDays(500)]);
    CheckEvent::create(['check_id' => $check->id, 'from_status' => 'up', 'to_status' => 'down', 'at' => now()->subDays(10)]);

    $this->artisan('phare:checks:prune')->assertSuccessful();

    expect(CheckEvent::count())->toBe(1);
});
