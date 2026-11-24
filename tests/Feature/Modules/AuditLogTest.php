<?php

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\Alertes\Models\NotificationChannel;
use App\Modules\Audit\Audit;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\Audit\Services\AuditLogger;
use App\Modules\Checks\Models\Check;
use App\Modules\Coffre\Models\VaultEntry;
use App\Modules\Groups\Models\Group;
use App\Modules\Hosts\Models\Host;
use Illuminate\Auth\Events\Failed;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Fortify\Events\TwoFactorAuthenticationConfirmed;
use Laravel\Fortify\Events\TwoFactorAuthenticationDisabled;

it('records sign-ins, failed attempts and sign-outs', function () {
    $user = User::factory()->create();

    $this->post(route('login'), ['email' => $user->email, 'password' => 'wrong-password']);
    $this->post(route('login'), ['email' => $user->email, 'password' => 'password']);
    $this->post(route('logout'));

    expect(AuditLog::where('action', 'auth.login_failed')->first()->subject_label)->toBe($user->email)
        ->and(AuditLog::where('action', 'auth.login')->first()->user_id)->toBe($user->id)
        ->and(AuditLog::where('action', 'auth.logout')->first()->user_id)->toBe($user->id)
        ->and(json_encode(AuditLog::all()))->not->toContain('wrong-password');
});

it('records two-factor changes', function () {
    $user = User::factory()->create();

    Event::dispatch(new TwoFactorAuthenticationConfirmed($user));
    Event::dispatch(new TwoFactorAuthenticationDisabled($user));

    expect(AuditLog::pluck('action')->all())->toEqualCanonicalizing(['settings.two_factor_enabled', 'settings.two_factor_disabled']);
});

it('records created, updated and deleted models with changed attribute names only', function () {
    $this->actingAs($admin = User::factory()->admin()->create());

    $group = Group::factory()->create(['name' => 'Acme']);
    $check = Check::factory()->create(['name' => 'Site A', 'target' => 'https://secret-host.example']);
    $check->update(['name' => 'Site B', 'target' => 'https://other-secret.example']);
    $check->delete();

    $created = AuditLog::where('action', 'check.created')->firstOrFail();
    $updated = AuditLog::where('action', 'check.updated')->firstOrFail();

    expect(AuditLog::where('action', 'group.created')->first()->subject_label)->toBe($group->name)
        ->and($created->user_id)->toBe($admin->id)
        ->and($created->subject_label)->toBe('Site A')
        ->and($updated->meta['changed'])->toBe(['name', 'target'])
        ->and(json_encode($updated->meta))->not->toContain('other-secret')
        ->and(AuditLog::where('action', 'check.deleted')->first()->subject_label)->toBe('Site B');
});

it('records user role changes and deletions, and password changes without the password', function () {
    $this->actingAs($admin = User::factory()->admin()->create());
    $tech = User::factory()->tech()->create();

    $this->patch(route('users.update', $tech), ['role' => 'viewer', 'password' => 'Str0ng!Passw0rd'])->assertRedirect();
    $this->delete(route('users.destroy', $tech))->assertRedirect();

    $role = AuditLog::where('action', 'user.role_changed')->firstOrFail();

    expect($role->meta['from'])->toBe(UserRole::Tech->value)
        ->and($role->meta['to'])->toBe(UserRole::Viewer->value)
        ->and(AuditLog::where('action', 'password.changed')->where('subject_id', $tech->id)->exists())->toBeTrue()
        ->and(AuditLog::where('action', 'user.deleted')->first()->subject_label)->toBe($tech->name)
        ->and(json_encode(AuditLog::all()))->not->toContain('Str0ng!Passw0rd');
});

it('records a password change by the owner after a reset, attributed to them', function () {
    $user = User::factory()->create();

    $user->forceFill(['password' => 'brand-new-password'])->save();

    $log = AuditLog::where('action', 'password.changed')->firstOrFail();
    expect($log->user_id)->toBe($user->id)
        ->and(Hash::check('brand-new-password', $user->refresh()->password))->toBeTrue();
});

it('ignores changes made without a signed-in user and system-only attribute updates', function () {
    $host = Host::factory()->create();
    $host->update(['name' => 'Renamed by a script']);
    expect(AuditLog::count())->toBe(0);

    $this->actingAs(User::factory()->admin()->create());
    $host->forceFill(['last_seen_at' => now(), 'agent_version' => '1.2.3'])->save();
    expect(AuditLog::count())->toBe(0);
});

it('never stores secrets in meta, whatever the caller passes', function () {
    $this->actingAs(User::factory()->admin()->create());

    Audit::record('vault.revealed', null, [
        'password' => 'hunter2', 'api_token' => 'pha_abc', 'enrollment_token' => 'phe_abc', 'secret' => 'S3cr3t',
        'vault_value' => 'v', 'config' => ['token' => 'x'], 'channel' => 'slack', 'long' => str_repeat('a', 500),
    ]);

    $meta = AuditLog::firstOrFail()->meta;
    expect(array_keys($meta))->toEqualCanonicalizing(['channel', 'long', 'actor'])
        ->and(strlen($meta['long']))->toBe(AuditLogger::MAX_STRING_LENGTH);
});

it('does not record anything for channel config values', function () {
    $this->actingAs(User::factory()->admin()->create());

    $channel = NotificationChannel::create(['name' => 'Ops', 'type' => 'slack', 'config' => ['url' => 'https://hooks.example/SECRET'], 'enabled' => true]);
    $channel->update(['config' => ['url' => 'https://hooks.example/OTHER']]);

    expect(json_encode(AuditLog::all()))->not->toContain('SECRET')->not->toContain('OTHER')
        ->and(AuditLog::where('action', 'channel.updated')->first()->meta['changed'])->toBe(['config']);
});

it('never throws when the log cannot be written', function () {
    Schema::drop('audit_logs');

    expect(app(AuditLogger::class)->record('check.created'))->toBeNull();
    expect(fn () => Audit::record('check.created'))->not->toThrow(Throwable::class);
});

it('records a vault reveal in the audit log without the secret', function () {
    config(['vault.master_key' => base64_encode(random_bytes(32))]);
    $this->actingAs($tech = User::factory()->tech()->create());

    $this->post(route('coffre.store'), ['label' => 'OVH', 'secret' => 'S3cr3t-Value!', 'password' => 'password']);
    $entry = VaultEntry::firstOrFail();
    $this->postJson(route('coffre.reveal', $entry), ['password' => 'password'])->assertOk();

    $log = AuditLog::where('action', 'vault.revealed')->firstOrFail();
    expect($log->subject_label)->toBe('OVH')->and($log->user_id)->toBe($tech->id)
        ->and(AuditLog::where('action', 'vault.created')->exists())->toBeTrue()
        ->and(json_encode(AuditLog::all()))->not->toContain('S3cr3t-Value!');
});

it('prunes entries older than the retention period', function () {
    config(['phare.audit_retention_days' => 30]);
    $old = AuditLog::create(['action' => 'check.created', 'created_at' => now()->subDays(31)]);
    $recent = AuditLog::create(['action' => 'check.created', 'created_at' => now()->subDays(29)]);

    $this->artisan('phare:audit:prune')->assertSuccessful();

    expect(AuditLog::find($old->id))->toBeNull()->and(AuditLog::find($recent->id))->not->toBeNull();
});

it('is admin only', function () {
    $this->actingAs(User::factory()->tech()->create())->get(route('audit.index'))->assertForbidden();
    $this->actingAs(User::factory()->create(['role' => UserRole::Viewer]))->get(route('audit.index'))->assertForbidden();
    $this->actingAs(User::factory()->admin()->create())->get(route('audit.index'))->assertOk();
});

it('renders plain sentences and filters by user, family and dates', function () {
    $admin = User::factory()->admin()->create(['name' => 'Marie']);
    $other = User::factory()->tech()->create(['name' => 'Paul']);
    AuditLog::create(['user_id' => $admin->id, 'action' => 'check.created', 'subject_label' => 'Site A', 'created_at' => now()->subDays(2)]);
    AuditLog::create(['user_id' => $other->id, 'action' => 'group.created', 'subject_label' => 'Acme', 'created_at' => now()]);
    AuditLog::create(['action' => 'user.deleted', 'subject_label' => 'Gone', 'meta' => ['actor' => 'Ex Admin'], 'created_at' => now()->subDays(10)]);

    $this->actingAs($admin);

    $sentences = fn (array $query = []) => $this->get(route('audit.index', $query))->viewData('page')['props']['logs']['data'];

    expect(collect($sentences())->pluck('sentence')->all())->toBe([
        'Paul created the group Acme', 'Marie created the monitor Site A', 'Ex Admin deleted the account Gone',
    ])
        ->and(collect($sentences(['user_id' => $other->id]))->pluck('sentence')->all())->toBe(['Paul created the group Acme'])
        ->and(collect($sentences(['family' => 'check']))->pluck('sentence')->all())->toBe(['Marie created the monitor Site A'])
        ->and(collect($sentences(['from' => now()->subDays(3)->toDateString(), 'to' => now()->subDays(1)->toDateString()]))->pluck('sentence')->all())->toBe(['Marie created the monitor Site A']);

    $this->get(route('audit.index', ['family' => 'nope']))->assertSessionHasErrors('family');
});

it('paginates by 50', function () {
    $admin = User::factory()->admin()->create();
    foreach (range(1, 55) as $i) {
        AuditLog::create(['action' => 'check.created', 'created_at' => now()]);
    }

    $this->actingAs($admin)->get(route('audit.index'))->assertInertia(fn (Assert $page) => $page
        ->component('audit/Index')->has('logs.data', 50)->where('logs.total', 55)->whereNot('logs.next', null));
});

it('keeps the failed-login listener working with unknown credentials shapes', function () {
    Event::dispatch(new Failed('web', null, ['password' => 'x']));

    expect(AuditLog::firstOrFail()->subject_label)->toBeNull();
});
