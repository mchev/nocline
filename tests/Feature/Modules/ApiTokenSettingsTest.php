<?php

use App\Models\User;
use App\Modules\Api\Models\ApiToken;

it('lets any user create a token and shows the secret once, never stored in clear', function () {
    $user = User::factory()->create();   // a viewer

    $response = $this->actingAs($user)->post(route('api-tokens.store'), ['name' => 'CRM', 'access' => 'write', 'expires_in_days' => 30]);
    $response->assertRedirect(route('api-tokens.index'));

    $token = ApiToken::first();
    expect($token->user_id)->toBe($user->id)
        ->and($token->abilities)->toBe(['read', 'write'])
        ->and($token->expires_at->isFuture())->toBeTrue();

    $secret = null;
    $this->get(route('api-tokens.index'))->assertInertia(function ($page) use (&$secret, $token) {
        $page->component('settings/ApiTokens')
            ->where('new_secret', function ($value) use (&$secret, $token) {
                $secret = $value;

                return str_starts_with($value, 'phr_') && ApiToken::hashSecret($value) === $token->token_hash;
            })
            ->has('tokens', 1)
            ->where('tokens.0.name', 'CRM')
            ->where('tokens.0.can_write', true)
            ->missing('tokens.0.token_hash');
    });

    $this->get(route('api-tokens.index'))->assertInertia(fn ($page) => $page->where('new_secret', null));
    $this->assertDatabaseMissing('api_tokens', ['token_hash' => $secret]);
});

it('creates read-only tokens without expiry by default', function () {
    $this->actingAs(User::factory()->create())->post(route('api-tokens.store'), ['name' => 'Dash', 'access' => 'read']);

    $token = ApiToken::first();
    expect($token->abilities)->toBe(['read'])->and($token->expires_at)->toBeNull();
});

it('validates the token form', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('api-tokens.store'), ['name' => '', 'access' => 'root', 'expires_in_days' => 0])
        ->assertSessionHasErrors(['name', 'access', 'expires_in_days']);
});

it('lists and revokes only your own tokens', function () {
    $me = User::factory()->create();
    $mine = ApiToken::factory()->for($me)->create();
    $theirs = ApiToken::factory()->withSecret('phr_'.str_repeat('z', 40))->create();

    $this->actingAs($me)->get(route('api-tokens.index'))->assertInertia(fn ($page) => $page->has('tokens', 1)->where('tokens.0.id', $mine->id));

    $this->delete(route('api-tokens.destroy', $theirs))->assertForbidden();
    expect(ApiToken::count())->toBe(2);

    $this->delete(route('api-tokens.destroy', $mine))->assertRedirect();
    expect(ApiToken::pluck('id')->all())->toBe([$theirs->id]);
});

it('requires authentication', function () {
    $this->get(route('api-tokens.index'))->assertRedirect(route('login'));
});
