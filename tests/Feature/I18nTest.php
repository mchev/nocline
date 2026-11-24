<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('ships a valid french language file', function () {
    $data = json_decode(file_get_contents(lang_path('fr.json')), true, flags: JSON_THROW_ON_ERROR);

    expect($data)->toBeArray()
        ->and($data['_language'])->toBe('Français')
        ->and($data['Incidents'])->toBe('Incidents')
        ->and($data['Open incidents'])->toBe('Incidents ouverts');
});

it('shares english by default without translations', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('locale', 'en')
            ->missing('translations.Open incidents')
            ->has('locales.fr'));
});

it('shares french translations with a user whose locale is fr', function () {
    $user = User::factory()->create();
    $user->forceFill(['locale' => 'fr'])->save();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('locale', 'fr')
            ->where('translations.Open incidents', 'Incidents ouverts'));
});

it('stores the chosen locale in session and on the user', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('locale.update'), ['locale' => 'fr'])
        ->assertRedirect()
        ->assertSessionHas('locale', 'fr');

    expect($user->fresh()->locale)->toBe('fr');
});

it('rejects unknown locales', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('locale.update'), ['locale' => 'xx'])
        ->assertSessionHasErrors('locale');

    expect($user->fresh()->locale)->not->toBe('xx');
});
