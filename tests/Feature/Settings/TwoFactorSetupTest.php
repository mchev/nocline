<?php

use App\Models\User;
use App\Modules\Audit\Models\AuditLog;
use PragmaRX\Google2FA\Google2FA;

it('lets a user enable two-factor authentication with a QR code and recovery codes, then disable it', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->withSession(['auth.password_confirmed_at' => time()]);

    $this->postJson(route('two-factor.enable'))->assertOk();
    $user->refresh();
    expect($user->two_factor_secret)->not->toBeNull()->and($user->hasEnabledTwoFactorAuthentication())->toBeFalse();

    $this->getJson(route('two-factor.qr-code'))->assertOk()->assertJsonStructure(['svg', 'url']);
    $this->getJson(route('two-factor.secret-key'))->assertOk()->assertJsonStructure(['secretKey']);
    $this->getJson(route('two-factor.recovery-codes'))->assertOk()->assertJsonCount(8);

    $this->postJson(route('two-factor.confirm'), ['code' => '000000'])->assertUnprocessable();

    $code = app(Google2FA::class)->getCurrentOtp(decrypt($user->two_factor_secret));
    $this->postJson(route('two-factor.confirm'), ['code' => $code])->assertOk();

    expect($user->refresh()->hasEnabledTwoFactorAuthentication())->toBeTrue()
        ->and(AuditLog::where('action', 'settings.two_factor_enabled')->where('user_id', $user->id)->exists())->toBeTrue();

    $this->deleteJson(route('two-factor.disable'))->assertOk();

    expect($user->refresh()->two_factor_secret)->toBeNull()
        ->and(AuditLog::where('action', 'settings.two_factor_disabled')->exists())->toBeTrue();
});

it('asks to confirm the password before touching two-factor settings', function () {
    $this->actingAs(User::factory()->create())->postJson(route('two-factor.enable'))->assertStatus(423);
});

it('asks a user with two-factor for a code at sign-in', function () {
    $user = User::factory()->withTwoFactor()->create();

    $this->post(route('login'), ['email' => $user->email, 'password' => 'password'])->assertRedirect(route('two-factor.login'));
    $this->assertGuest();
});
