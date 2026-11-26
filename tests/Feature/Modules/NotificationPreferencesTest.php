<?php

use App\Models\User;
use App\Modules\Alertes\Models\AlertDelivery;
use App\Modules\Alertes\Models\NotificationChannel;
use App\Modules\Alertes\Notifications\MessageMail;
use App\Modules\Alertes\Services\AlertDispatcher;
use App\Modules\Alertes\Services\NotificationPreferences;
use App\Modules\Checks\Models\Check;
use App\Modules\Incidents\Models\Incident;
use App\Modules\Renewals\Models\Renewal;
use App\Modules\Renewals\Models\RenewalReminder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    Mail::fake();
    NotificationChannel::create(['name' => 'Ops', 'type' => 'email', 'config' => ['to' => 'ops@example.com']]);
    Http::fake(['*' => Http::response('', 204)]);
});

it('lets admins update notification preferences', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->patch(route('alertes.notification-preferences.update'), [
            'incidents' => ['opened' => true, 'resolved' => false],
            'renewals' => [
                'ssl' => 'expired_only',
                'domain' => 'off',
                'contract' => 'thresholds',
                'other' => 'off',
            ],
        ])
        ->assertRedirect();

    $prefs = app(NotificationPreferences::class)->all();

    expect($prefs['incidents']['resolved'])->toBeFalse()
        ->and($prefs['renewals']['ssl'])->toBe('expired_only')
        ->and($prefs['renewals']['domain'])->toBe('off');
});

it('skips incident alerts when opened notifications are disabled', function () {
    app(NotificationPreferences::class)->save([
        'incidents' => ['opened' => false, 'resolved' => true],
        'renewals' => [],
    ]);

    $check = Check::factory()->create();
    $incident = Incident::factory()->create(['check_id' => $check->id]);

    app(AlertDispatcher::class)->dispatch($incident, 'opened');

    expect(AlertDelivery::query()->count())->toBe(0);
});

it('skips renewal reminders when the kind is disabled', function () {
    app(NotificationPreferences::class)->save([
        'incidents' => ['opened' => true, 'resolved' => true],
        'renewals' => [
            'ssl' => 'thresholds',
            'domain' => 'off',
            'contract' => 'thresholds',
            'other' => 'thresholds',
        ],
    ]);

    Renewal::create([
        'kind' => 'domain',
        'label' => 'example.com',
        'expires_on' => now()->addDays(5)->toDateString(),
    ]);

    $this->artisan('nocline:renewals:remind')->assertSuccessful();

    Mail::assertNothingSent();
});

it('only sends ssl expired alerts when mode is expired only', function () {
    app(NotificationPreferences::class)->save([
        'incidents' => ['opened' => true, 'resolved' => true],
        'renewals' => [
            'ssl' => 'expired_only',
            'domain' => 'thresholds',
            'contract' => 'thresholds',
            'other' => 'thresholds',
        ],
    ]);

    Renewal::create([
        'kind' => 'ssl',
        'label' => 'www.example.com',
        'expires_on' => now()->addDays(20)->toDateString(),
    ]);

    $this->artisan('nocline:renewals:remind');
    Mail::assertNothingSent();

    $expired = Renewal::create([
        'kind' => 'ssl',
        'label' => 'old.example.com',
        'expires_on' => now()->subDay()->toDateString(),
    ]);

    $this->artisan('nocline:renewals:remind');
    Mail::assertSent(MessageMail::class, 1);
    expect(RenewalReminder::query()->where('renewal_id', $expired->id)->where('days_before', 0)->exists())->toBeTrue();
});
