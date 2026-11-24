<?php

use App\Models\User;
use App\Modules\Api\Enums\ApiTokenAbility;
use App\Modules\Api\Models\ApiToken;
use Database\Factories\ApiTokenFactory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;

function apiBearer(string $secret = ApiTokenFactory::SECRET): array
{
    return ['Authorization' => "Bearer {$secret}"];
}

it('rejects a missing, malformed or unknown token with an identical 401', function (?string $header) {
    ApiToken::factory()->create();

    $response = $this->getJson(route('api.v1.me'), $header === null ? [] : ['Authorization' => $header]);

    $response->assertUnauthorized()->assertExactJson(['message' => 'Unauthenticated.']);
})->with([
    'missing' => [null],
    'wrong scheme' => ['Basic abc'],
    'no prefix' => ['Bearer notatoken'],
    'unknown' => ['Bearer phr_bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb'],
]);

it('rejects an expired token exactly like an unknown one', function () {
    ApiToken::factory()->create(['expires_at' => now()->subMinute()]);

    $this->getJson(route('api.v1.me'), apiBearer())->assertUnauthorized()->assertExactJson(['message' => 'Unauthenticated.']);
});

it('accepts a token until it is revoked', function () {
    $user = User::factory()->create();
    $token = ApiToken::factory()->for($user)->create(['expires_at' => now()->addDay()]);

    $this->getJson(route('api.v1.me'), apiBearer())->assertOk()->assertJsonPath('data.email', $user->email);

    $token->delete();

    $this->getJson(route('api.v1.me'), apiBearer())->assertUnauthorized();
});

it('stops accepting the tokens of a deleted user', function () {
    $user = User::factory()->create();
    ApiToken::factory()->for($user)->create();
    $user->delete();

    $this->getJson(route('api.v1.me'), apiBearer())->assertUnauthorized();
});

it('stores only the hash of the secret and never exposes it', function () {
    $user = User::factory()->create();
    ['token' => $token, 'secret' => $secret] = ApiToken::issue($user, 'CI', [ApiTokenAbility::Read]);

    expect($secret)->toStartWith('phr_')->toHaveLength(44)
        ->and($token->token_hash)->toBe(hash('sha256', $secret))
        ->and(json_encode($token->toArray()))->not->toContain($secret)->not->toContain($token->token_hash);
    $this->assertDatabaseMissing('api_tokens', ['token_hash' => $secret]);

    $this->getJson(route('api.v1.me'), apiBearer($secret))->assertOk()
        ->assertJsonMissingPath('data.token.token_hash')
        ->assertJsonPath('data.token.name', 'CI');
});

it('forbids writes with a read-only token', function () {
    ApiToken::factory()->for(User::factory()->admin())->create();

    $this->postJson(route('api.v1.groups.store'), ['name' => 'Acme'], apiBearer())->assertForbidden();
    $this->getJson(route('api.v1.groups.index'), apiBearer())->assertOk();
});

it('combines the token ability with the owner role: a viewer with a write token is still forbidden', function () {
    ApiToken::factory()->for(User::factory()->create())->writable()->create();

    $this->postJson(route('api.v1.groups.store'), ['name' => 'Acme'], apiBearer())->assertForbidden();
    $this->postJson(route('api.v1.monitors.website'), ['url' => 'example.com'], apiBearer())->assertForbidden();
    $this->getJson(route('api.v1.groups.index'), apiBearer())->assertOk();
});

it('updates last_used_at at most once per minute', function () {
    $token = ApiToken::factory()->create();

    $this->travelTo(now()->startOfMinute());
    $this->getJson(route('api.v1.me'), apiBearer())->assertOk();
    $first = $token->fresh()->last_used_at;
    expect($first)->not->toBeNull();

    $this->travel(30)->seconds();
    $this->getJson(route('api.v1.me'), apiBearer())->assertOk();
    expect($token->fresh()->last_used_at->eq($first))->toBeTrue();

    $this->travel(31)->seconds();
    $this->getJson(route('api.v1.me'), apiBearer())->assertOk();
    expect($token->fresh()->last_used_at->gt($first))->toBeTrue();
});

it('rate limits each token separately with a JSON 429', function () {
    Cache::flush();
    ApiToken::factory()->create();
    ApiToken::factory()->withSecret('phr_cccccccccccccccccccccccccccccccccccccccc')->create();

    for ($i = 0; $i < 120; $i++) {
        $this->getJson(route('api.v1.me'), apiBearer())->assertOk();
    }

    $this->getJson(route('api.v1.me'), apiBearer())
        ->assertStatus(429)->assertJson(['message' => 'Too many requests.'])->assertHeader('Retry-After');
    $this->getJson(route('api.v1.me'), apiBearer('phr_cccccccccccccccccccccccccccccccccccccccc'))->assertOk();
});

it('throttles repeated failed authentications per IP', function () {
    RateLimiter::clear('api-token-failures:127.0.0.1');

    for ($i = 0; $i < 30; $i++) {
        $this->getJson(route('api.v1.me'), apiBearer('phr_dddddddddddddddddddddddddddddddddddddddd'))->assertUnauthorized();
    }

    $this->getJson(route('api.v1.me'), apiBearer('phr_dddddddddddddddddddddddddddddddddddddddd'))->assertStatus(429);
});

it('returns a JSON 404 without leaking internals for unknown resources', function () {
    ApiToken::factory()->create();

    $this->getJson('/api/v1/checks/999', apiBearer())->assertNotFound()
        ->assertExactJson(['message' => 'Not found.']);
});
