<?php

use App\Models\User;
use App\Modules\Checks\Enums\CheckStatus;
use App\Modules\Checks\Models\Check;
use App\Modules\Checks\Services\CheckResultRecorder;
use App\Modules\Groups\Models\Group;
use App\Modules\Incidents\Events\IncidentOpened;
use App\Modules\Incidents\Models\Incident;
use App\Modules\Maintenance\Enums\MaintenanceRecurrence;
use App\Modules\Maintenance\Models\MaintenanceWindow;
use App\Modules\Maintenance\Services\MaintenanceChecker;
use App\Modules\Wallboard\Models\Wallboard;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

function maintenanceAt(string $at): CarbonImmutable
{
    return CarbonImmutable::parse($at, 'UTC');
}

it('is active only inside a one-off window', function () {
    $check = Check::factory()->create();
    MaintenanceWindow::factory()->create(['starts_at' => '2026-03-01 10:00', 'ends_at' => '2026-03-01 12:00']);
    $checker = new MaintenanceChecker;

    expect($checker->isActive($check, maintenanceAt('2026-03-01 09:59')))->toBeFalse()
        ->and($checker->isActive($check, maintenanceAt('2026-03-01 10:00')))->toBeTrue()
        ->and($checker->isActive($check, maintenanceAt('2026-03-01 11:59')))->toBeTrue()
        ->and($checker->isActive($check, maintenanceAt('2026-03-01 12:01')))->toBeFalse();
});

it('repeats a daily window across midnight but never before its first start', function () {
    $check = Check::factory()->create();
    MaintenanceWindow::factory()->recurring(MaintenanceRecurrence::Daily)
        ->create(['starts_at' => '2026-03-01 23:00', 'ends_at' => '2026-03-02 01:00']);
    $checker = new MaintenanceChecker;

    expect($checker->isActive($check, maintenanceAt('2026-02-28 23:30')))->toBeFalse()
        ->and($checker->isActive($check, maintenanceAt('2026-03-01 23:30')))->toBeTrue()
        ->and($checker->isActive($check, maintenanceAt('2026-03-10 23:30')))->toBeTrue()
        ->and($checker->isActive($check, maintenanceAt('2026-03-11 00:30')))->toBeTrue()
        ->and($checker->isActive($check, maintenanceAt('2026-03-11 02:00')))->toBeFalse()
        ->and($checker->isActive($check, maintenanceAt('2026-03-11 22:59')))->toBeFalse();
});

it('repeats a weekly window on the same weekday only', function () {
    $check = Check::factory()->create();
    MaintenanceWindow::factory()->recurring(MaintenanceRecurrence::Weekly)
        ->create(['starts_at' => '2026-03-06 22:00', 'ends_at' => '2026-03-07 02:00']);
    $checker = new MaintenanceChecker;

    expect($checker->isActive($check, maintenanceAt('2026-03-13 23:00')))->toBeTrue()
        ->and($checker->isActive($check, maintenanceAt('2026-03-14 01:00')))->toBeTrue()
        ->and($checker->isActive($check, maintenanceAt('2026-03-12 23:00')))->toBeFalse()
        ->and($checker->isActive($check, maintenanceAt('2026-03-27 23:00')))->toBeTrue();
});

it('keeps the local time of day across a daylight-saving change', function () {
    config(['app.timezone' => 'Europe/Paris']);
    $window = MaintenanceWindow::factory()->recurring(MaintenanceRecurrence::Daily)->make([
        'starts_at' => CarbonImmutable::parse('2026-01-10 21:00', 'UTC'), // 22:00 in Paris
        'ends_at' => CarbonImmutable::parse('2026-01-10 22:00', 'UTC'),
    ]);

    expect($window->isActiveAt(maintenanceAt('2026-07-10 20:30')))->toBeTrue()  // 22:30 in Paris
        ->and($window->isActiveAt(maintenanceAt('2026-07-10 21:30')))->toBeFalse(); // 23:30 in Paris
});

it('scopes a window to everything, a group with its sub-groups, or one monitor', function () {
    $parent = Group::factory()->create();
    $child = Group::factory()->create(['parent_id' => $parent->id]);
    $other = Group::factory()->create();
    $inParent = Check::factory()->create(['group_id' => $parent->id]);
    $inChild = Check::factory()->create(['group_id' => $child->id]);
    $inOther = Check::factory()->create(['group_id' => $other->id]);
    $loose = Check::factory()->create();
    $checks = [$inParent, $inChild, $inOther, $loose];
    $checker = new MaintenanceChecker;

    $group = MaintenanceWindow::factory()->create(['group_id' => $parent->id]);
    expect($checker->activeCheckIds($checks))->toEqualCanonicalizing([$inParent->id, $inChild->id]);

    $group->delete();
    $one = MaintenanceWindow::factory()->create(['check_id' => $inOther->id]);
    expect($checker->activeCheckIds($checks))->toBe([$inOther->id]);

    $one->delete();
    MaintenanceWindow::factory()->create();
    expect($checker->activeCheckIds($checks))->toHaveCount(4);
});

it('resolves many checks with a bounded number of queries', function () {
    $parent = Group::factory()->create();
    $checks = Check::factory()->count(60)->create(['group_id' => $parent->id]);
    MaintenanceWindow::factory()->create(['group_id' => $parent->id]);
    MaintenanceWindow::factory()->recurring(MaintenanceRecurrence::Daily)->create();

    DB::enableQueryLog();
    $ids = (new MaintenanceChecker)->activeCheckIds($checks);
    $queries = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($ids)->toHaveCount(60)->and($queries)->toBeLessThanOrEqual(2);
});

it('lists upcoming and active occurrences soonest first', function () {
    MaintenanceWindow::factory()->create(['name' => 'Later', 'starts_at' => '2026-03-05 10:00', 'ends_at' => '2026-03-05 11:00']);
    MaintenanceWindow::factory()->create(['name' => 'Now', 'starts_at' => '2026-03-01 09:00', 'ends_at' => '2026-03-01 11:00']);
    MaintenanceWindow::factory()->create(['name' => 'Past', 'starts_at' => '2026-02-01 09:00', 'ends_at' => '2026-02-01 11:00']);
    MaintenanceWindow::factory()->create(['name' => 'Too far', 'starts_at' => '2026-06-05 10:00', 'ends_at' => '2026-06-05 11:00']);

    $upcoming = (new MaintenanceChecker)->upcoming(maintenanceAt('2026-03-01 10:00'), 14);

    expect(array_map(fn ($o) => $o['window']->name, $upcoming))->toBe(['Now', 'Later'])
        ->and($upcoming[0]['active'])->toBeTrue()
        ->and($upcoming[1]['active'])->toBeFalse();
});

it('opens no incident and sends no alert while a check is under maintenance', function () {
    Event::fake([IncidentOpened::class]);
    $check = Check::factory()->create(['failure_threshold' => 1]);
    MaintenanceWindow::factory()->create(['check_id' => $check->id]);

    $status = app(CheckResultRecorder::class)->record($check, false, null, 'boom');

    expect($status)->toBe(CheckStatus::Down)
        ->and($check->state()->first()->status)->toBe(CheckStatus::Down)
        ->and(Incident::count())->toBe(0);
    Event::assertNotDispatched(IncidentOpened::class);
});

it('behaves normally once the maintenance is over', function () {
    Event::fake([IncidentOpened::class]);
    $check = Check::factory()->create(['failure_threshold' => 1]);
    MaintenanceWindow::factory()->create(['check_id' => $check->id, 'starts_at' => now()->subHours(3), 'ends_at' => now()->subHour()]);

    app(CheckResultRecorder::class)->record($check, false, null, 'boom');

    expect(Incident::count())->toBe(1);
    Event::assertDispatched(IncidentOpened::class);
});

it('keeps an already open incident untouched when maintenance starts and resolves it on recovery', function () {
    $check = Check::factory()->create(['failure_threshold' => 1]);
    app(CheckResultRecorder::class)->record($check, false, null, 'first');
    MaintenanceWindow::factory()->create(['check_id' => $check->id]);

    app(CheckResultRecorder::class)->record($check, true);

    $incident = Incident::first();
    expect(Incident::count())->toBe(1)->and($incident->isOpen())->toBeFalse();
});

it('opens the incident of a check that is still down after the maintenance', function () {
    Event::fake([IncidentOpened::class]);
    $check = Check::factory()->create(['failure_threshold' => 1]);
    $window = MaintenanceWindow::factory()->create(['check_id' => $check->id]);
    app(CheckResultRecorder::class)->record($check, false, null, 'boom');

    $this->artisan('phare:maintenance:catch-up')->assertSuccessful();
    expect(Incident::count())->toBe(0);

    $window->update(['ends_at' => now()->subMinute()]);
    $this->artisan('phare:maintenance:catch-up')->assertSuccessful();
    $this->artisan('phare:maintenance:catch-up')->assertSuccessful();

    expect(Incident::count())->toBe(1)->and(Incident::first()->last_error)->toBe('boom');
    Event::assertDispatchedTimes(IncidentOpened::class, 1);
});

it('marks checks under maintenance on the checks page without hiding their real state', function () {
    $check = Check::factory()->create(['name' => 'A first', 'failure_threshold' => 1]);
    Check::factory()->create(['name' => 'B second']);
    app(CheckResultRecorder::class)->record($check, false, null, 'boom');
    MaintenanceWindow::factory()->create(['check_id' => $check->id]);

    $this->actingAs(User::factory()->create())
        ->get(route('checks.index'))
        ->assertInertia(fn ($p) => $p->component('checks/Index')
            ->where('checks.0.status', 'down')
            ->where('checks.0.in_maintenance', true)
            ->where('checks.1.in_maintenance', false));
});

it('separates checks in maintenance from problems on the dashboard', function () {
    $quiet = Check::factory()->create(['failure_threshold' => 1]);
    $broken = Check::factory()->create(['failure_threshold' => 1]);
    app(CheckResultRecorder::class)->record($quiet, false, null, 'boom');
    app(CheckResultRecorder::class)->record($broken, false, null, 'boom');
    MaintenanceWindow::factory()->create(['check_id' => $quiet->id]);

    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertInertia(fn ($p) => $p->component('Dashboard')
            ->has('problems', 1)->where('problems.0.id', $broken->id)
            ->has('maintenance', 1)->where('maintenance.0.id', $quiet->id)
            ->where('checks.down', 1)->where('checks.maintenance', 1));
});

it('does not list checks in maintenance as failing on the wallboard', function () {
    ['token' => $token] = Wallboard::createWithToken(['name' => 'NOC']);
    $quiet = Check::factory()->create(['failure_threshold' => 1]);
    app(CheckResultRecorder::class)->record($quiet, false, null, 'boom');
    MaintenanceWindow::factory()->create(['check_id' => $quiet->id]);

    $this->getJson(route('api.wallboard', $token))
        ->assertJsonPath('summary.down', 0)
        ->assertJsonPath('summary.maintenance', 1)
        ->assertJsonCount(0, 'checks_failing');
});

it('lets tech and admin manage windows and validates them', function () {
    $this->actingAs(User::factory()->tech()->create());
    $payload = ['name' => 'Upgrade', 'starts_at' => now()->toIso8601String(), 'ends_at' => now()->addHour()->toIso8601String(), 'recurrence' => 'none'];

    $this->post(route('maintenance.store'), $payload)->assertRedirect();
    $window = MaintenanceWindow::first();
    expect($window->name)->toBe('Upgrade')->and($window->created_by)->not->toBeNull();

    $this->patch(route('maintenance.update', $window), ['name' => 'Renamed'] + $payload)->assertRedirect();
    expect($window->refresh()->name)->toBe('Renamed');

    $this->post(route('maintenance.store'), ['ends_at' => now()->subHour()->toIso8601String()] + $payload)->assertSessionHasErrors('ends_at');
    $this->post(route('maintenance.store'), ['recurrence' => 'daily', 'ends_at' => now()->addDay()->toIso8601String()] + $payload)->assertSessionHasErrors('ends_at');
    $this->post(route('maintenance.store'), ['group_id' => Group::factory()->create()->id, 'check_id' => Check::factory()->create()->id] + $payload)->assertSessionHasErrors('group_id');

    $this->delete(route('maintenance.destroy', $window))->assertRedirect();
    expect(MaintenanceWindow::count())->toBe(0);
});

it('lets viewers read but not write', function () {
    $window = MaintenanceWindow::factory()->create();
    $this->actingAs(User::factory()->create());

    $this->get(route('maintenance.index'))->assertOk()
        ->assertInertia(fn ($p) => $p->component('maintenance/Index')->has('windows', 1)->where('windows.0.state', 'active'));
    $this->post(route('maintenance.store'), [])->assertForbidden();
    $this->patch(route('maintenance.update', $window), [])->assertForbidden();
    $this->delete(route('maintenance.destroy', $window))->assertForbidden();
});

it('redirects guests to login', function () {
    $this->get(route('maintenance.index'))->assertRedirect(route('login'));
});
