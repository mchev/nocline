<?php

use App\Models\User;
use App\Modules\Alertes\Models\AlertDelivery;
use App\Modules\Alertes\Models\NotificationChannel;
use App\Modules\Alertes\Services\AlertDispatcher;
use App\Modules\Checks\Enums\CheckType;
use App\Modules\Checks\Models\Check;
use App\Modules\Checks\Services\CheckResultRecorder;
use App\Modules\Groups\Models\Group;
use App\Modules\Incidents\Models\Incident;
use Illuminate\Support\Facades\DB;

it('routes alerts only to channels linked to the check group', function () {
    $group = Group::factory()->create();
    $slack = NotificationChannel::create(['name' => 'Slack client', 'type' => 'webhook', 'config' => ['url' => 'https://hooks.test/a'], 'enabled' => true]);
    $global = NotificationChannel::create(['name' => 'Ops mail', 'type' => 'email', 'config' => ['to' => 'ops@example.com'], 'enabled' => true]);

    DB::table('group_notification_channel')->insert([
        ['group_id' => $group->id, 'notification_channel_id' => $slack->id],
    ]);

    $check = Check::factory()->create([
        'type' => CheckType::Http,
        'group_id' => $group->id,
        'target' => 'https://example.com',
    ]);

    $incident = Incident::factory()->create(['check_id' => $check->id]);

    app(AlertDispatcher::class)->dispatch($incident, 'opened');

    expect(AlertDelivery::query()->where('channel_id', $slack->id)->exists())->toBeTrue()
        ->and(AlertDelivery::query()->where('channel_id', $global->id)->exists())->toBeFalse();
});

it('skips dispatch while alerts are snoozed on the check', function () {
    $check = Check::factory()->create([
        'alerts_snoozed_until' => now()->addHour(),
    ]);
    $incident = Incident::factory()->create(['check_id' => $check->id]);

    app(AlertDispatcher::class)->dispatch($incident, 'opened');

    expect(AlertDelivery::query()->count())->toBe(0);
});

it('lets a tech snooze alerts from the check page', function () {
    $user = User::factory()->tech()->create();
    $check = Check::factory()->create();

    $this->actingAs($user)
        ->post(route('checks.snooze', $check), ['hours' => 4])
        ->assertRedirect();

    expect($check->refresh()->alerts_snoozed_until)->not->toBeNull();
});

it('marks a check down after sustained slow http responses', function () {
    $check = Check::factory()->create([
        'type' => CheckType::Http,
        'target' => 'https://example.com',
        'failure_threshold' => 2,
        'config' => ['latency_alert_ms' => 1000, 'latency_sustain' => 2],
    ]);

    $recorder = app(CheckResultRecorder::class);
    $recorder->record($check, true, 1500, null);
    $recorder->record($check->fresh(), true, 1600, null);

    expect($check->fresh()->state?->status->value)->toBe('down');
});
