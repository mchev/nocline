<?php

use App\Models\User;
use App\Modules\Checks\Enums\CheckCategory;
use App\Modules\Checks\Enums\CheckType;
use App\Modules\Checks\Models\Check;
use App\Modules\Renewals\Models\Renewal;
use App\Modules\Renewals\Services\CheckRenewalCleanup;
use App\Modules\Renewals\Services\RegistrableDomain;

it('cleans renewals via the cleanup service', function () {
    $check = Check::factory()->create([
        'type' => CheckType::Http,
        'target' => 'https://solo.mycorp.net',
    ]);
    $domain = RegistrableDomain::from('solo.mycorp.net');

    Renewal::query()->create([
        'check_id' => $check->id,
        'kind' => 'ssl',
        'source' => 'auto',
        'label' => 'SSL solo.mycorp.net',
        'expires_on' => now()->addMonths(3),
    ]);
    Renewal::query()->create([
        'kind' => 'domain',
        'source' => 'auto',
        'label' => $domain,
        'expires_on' => now()->addMonths(6),
    ]);

    app(CheckRenewalCleanup::class)->forDeletedCheck($check);

    expect(Renewal::query()->count())->toBe(0);
});

it('removes ssl and domain renewals when the last site for that domain is deleted', function () {
    $user = User::factory()->admin()->create();
    $check = Check::factory()->create([
        'type' => CheckType::Http,
        'category' => CheckCategory::Website,
        'target' => 'https://solo.mycorp.net',
    ]);
    $domain = RegistrableDomain::from('solo.mycorp.net');

    Renewal::query()->create([
        'check_id' => $check->id,
        'kind' => 'ssl',
        'source' => 'auto',
        'label' => 'SSL solo.mycorp.net',
        'expires_on' => now()->addMonths(3),
    ]);
    Renewal::query()->create([
        'kind' => 'domain',
        'source' => 'auto',
        'label' => $domain,
        'expires_on' => now()->addMonths(6),
    ]);

    $this->actingAs($user)->delete(route('checks.destroy', $check))->assertRedirect();

    expect(Renewal::query()->count())->toBe(0);
});

it('keeps a shared domain renewal when another site still monitors that domain', function () {
    $user = User::factory()->admin()->create();
    $a = Check::factory()->create([
        'target' => 'https://www.example.com',
        'type' => CheckType::Http,
    ]);
    Check::factory()->create([
        'target' => 'https://shop.example.com',
        'type' => CheckType::Http,
    ]);

    Renewal::query()->create([
        'kind' => 'domain',
        'source' => 'auto',
        'label' => 'example.com',
        'expires_on' => now()->addYear(),
    ]);

    $this->actingAs($user)->delete(route('checks.destroy', $a))->assertRedirect();

    expect(Renewal::query()->where('kind', 'domain')->where('label', 'example.com')->exists())->toBeTrue();
});
