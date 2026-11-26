<?php

use App\Models\User;
use App\Modules\Api\Models\ApiToken;
use App\Modules\Checks\Enums\CheckType;
use App\Modules\Checks\Models\Check;
use App\Modules\Groups\Models\Group;
use App\Modules\Hosts\Models\Host;
use Database\Factories\ApiTokenFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Symfony\Component\Yaml\Yaml;

function apiAs(User $user, bool $write = true): void
{
    $factory = ApiToken::factory()->for($user);
    ($write ? $factory->writable() : $factory)->create();
}

function apiAuth(): array
{
    return ['Authorization' => 'Bearer '.ApiTokenFactory::SECRET];
}

it('lists, creates, shows, updates and deletes checks as an admin', function () {
    apiAs(User::factory()->admin()->create());
    $group = Group::factory()->create();

    $created = $this->postJson('/api/v1/checks', [
        'name' => 'Shop', 'type' => 'http', 'target' => 'https://shop.test', 'group_id' => $group->id,
        'category' => 'website', 'tags' => ['prod'], 'interval_sec' => 120,
    ], apiAuth())->assertCreated()
        ->assertJsonPath('data.name', 'Shop')
        ->assertJsonPath('data.status', 'unknown')
        ->assertJsonPath('data.group_id', $group->id)
        ->assertJsonPath('data.tags', ['prod'])
        ->assertJsonPath('data.interval_sec', 120);
    $id = $created->json('data.id');

    $this->getJson('/api/v1/checks', apiAuth())->assertOk()
        ->assertJsonCount(1, 'data')->assertJsonStructure(['data', 'links', 'meta' => ['total', 'per_page']]);
    $this->getJson("/api/v1/checks/{$id}", apiAuth())->assertOk()->assertJsonPath('data.target', 'https://shop.test');

    $this->patchJson("/api/v1/checks/{$id}", ['enabled' => false, 'interval_sec' => 300], apiAuth())
        ->assertOk()->assertJsonPath('data.enabled', false)->assertJsonPath('data.interval_sec', 300)
        ->assertJsonPath('data.name', 'Shop')->assertJsonPath('data.target', 'https://shop.test');

    $this->deleteJson("/api/v1/checks/{$id}", [], apiAuth())->assertNoContent();
    expect(Check::count())->toBe(0);
});

it('filters the check list', function () {
    apiAs(User::factory()->tech()->create());
    $group = Group::factory()->create();
    Check::factory()->create(['group_id' => $group->id]);
    Check::factory()->create(['enabled' => false]);
    Check::factory()->heartbeat()->create();

    $this->getJson("/api/v1/checks?group_id={$group->id}", apiAuth())->assertJsonCount(1, 'data');
    $this->getJson('/api/v1/checks?enabled=0', apiAuth())->assertJsonCount(1, 'data');
    $this->getJson('/api/v1/checks?type=heartbeat', apiAuth())->assertJsonCount(1, 'data');
    $this->getJson('/api/v1/checks?type=nope', apiAuth())->assertUnprocessable()->assertJsonValidationErrors('type');
});

it('returns 422 with field errors for an invalid check payload', function () {
    apiAs(User::factory()->tech()->create());

    $this->postJson('/api/v1/checks', ['type' => 'http', 'interval_sec' => 5, 'group_id' => 999], apiAuth())
        ->assertUnprocessable()
        ->assertJsonStructure(['message', 'errors' => ['name', 'target', 'interval_sec', 'group_id']]);
});

it('requires the same conditional fields as the web form on PATCH', function () {
    apiAs(User::factory()->tech()->create());
    $check = Check::factory()->create();

    $this->patchJson("/api/v1/checks/{$check->id}", ['type' => 'agent'], apiAuth())
        ->assertUnprocessable()->assertJsonValidationErrors('host_id');

    $host = Host::factory()->create();
    $this->patchJson("/api/v1/checks/{$check->id}", ['type' => 'agent', 'host_id' => $host->id], apiAuth())
        ->assertOk()->assertJsonPath('data.type', 'agent');
});

it('applies the owner role on checks: tech cannot delete, viewer cannot create', function () {
    apiAs(User::factory()->tech()->create());
    $check = Check::factory()->create();

    $this->deleteJson("/api/v1/checks/{$check->id}", [], apiAuth())->assertForbidden();
    expect(Check::count())->toBe(1);

    ApiToken::query()->delete();
    apiAs(User::factory()->create());
    $this->postJson('/api/v1/checks', ['name' => 'x', 'type' => 'tcp', 'target' => 'a:1'], apiAuth())->assertForbidden();
    $this->patchJson("/api/v1/checks/{$check->id}", ['name' => 'y'], apiAuth())->assertForbidden();
});

it('exposes the current status of a check', function () {
    apiAs(User::factory()->create(), write: false);
    $check = Check::factory()->create();
    $check->state->update(['status' => 'down', 'last_error' => 'HTTP 500', 'consecutive_failures' => 2]);

    $this->getJson("/api/v1/checks/{$check->id}/status", apiAuth())->assertOk()
        ->assertJsonPath('data.check_id', $check->id)
        ->assertJsonPath('data.status', 'down')
        ->assertJsonPath('data.last_error', 'HTTP 500')
        ->assertJsonPath('data.consecutive_failures', 2);
});

it('only reveals the heartbeat url (a secret) to users who manage checks', function () {
    $check = Check::factory()->heartbeat()->create();

    apiAs(User::factory()->tech()->create(), write: false);
    $this->getJson("/api/v1/checks/{$check->id}", apiAuth())->assertJsonPath(
        'data.heartbeat_url',
        route('api.heartbeat', ['token' => $check->heartbeat_token]),
    );

    ApiToken::query()->delete();
    apiAs(User::factory()->create(), write: false);
    $this->getJson("/api/v1/checks/{$check->id}", apiAuth())->assertJsonMissingPath('data.heartbeat_url');
});

it('manages groups as a tree', function () {
    apiAs(User::factory()->tech()->create());

    $parent = $this->postJson('/api/v1/groups', ['name' => 'Acme'], apiAuth())->assertCreated()->json('data.id');
    $child = $this->postJson('/api/v1/groups', ['name' => 'Prod', 'parent_id' => $parent, 'color' => '#aabbcc'], apiAuth())
        ->assertCreated()->assertJsonPath('data.parent_id', $parent)->json('data.id');

    $this->patchJson("/api/v1/groups/{$child}", ['name' => 'Production'], apiAuth())
        ->assertOk()->assertJsonPath('data.name', 'Production')->assertJsonPath('data.parent_id', $parent);
    $this->patchJson("/api/v1/groups/{$parent}", ['parent_id' => $child], apiAuth())
        ->assertUnprocessable()->assertJsonValidationErrors('parent_id');
    $this->getJson("/api/v1/groups/{$child}", apiAuth())->assertOk();
    $this->getJson('/api/v1/groups', apiAuth())->assertJsonCount(2, 'data');

    $this->deleteJson("/api/v1/groups/{$parent}", [], apiAuth())->assertNoContent();
    expect(Group::find($child)->parent_id)->toBeNull();
});

it('validates groups', function () {
    apiAs(User::factory()->tech()->create());

    $this->postJson('/api/v1/groups', ['color' => 'red'], apiAuth())
        ->assertUnprocessable()->assertJsonValidationErrors(['name', 'color']);
});

it('lists hosts read-only without any credential', function () {
    apiAs(User::factory()->create(), write: false);
    Host::factory()->enrolled()->create(['name' => 'web-1']);

    $response = $this->getJson('/api/v1/hosts', apiAuth())->assertOk()->assertJsonPath('data.0.name', 'web-1')->assertJsonPath('data.0.enrolled', true);
    expect($response->getContent())->not->toContain('token_hash')->not->toContain(hash('sha256', 'pha_test'));
    $this->postJson('/api/v1/hosts', ['name' => 'x'], apiAuth())->assertStatus(405);
});

it('creates a website monitor like the guided screen', function () {
    apiAs(User::factory()->tech()->create());

    $this->postJson('/api/v1/monitors/website', ['url' => 'www.example.com', 'group_name' => 'Acme'], apiAuth())
        ->assertCreated()
        ->assertJsonPath('data.type', 'http')
        ->assertJsonPath('data.category', 'website')
        ->assertJsonPath('data.target', 'https://www.example.com')
        ->assertJsonPath('data.name', 'example.com')
        ->assertJsonPath('data.interval_sec', 60);

    expect(Group::where('name', 'Acme')->exists())->toBeTrue()
        ->and(Check::first()->type)->toBe(CheckType::Http);

    $this->postJson('/api/v1/monitors/website', ['url' => 'not a url'], apiAuth())
        ->assertUnprocessable()->assertJsonValidationErrors('url');
});

it('keeps list queries bounded regardless of the number of checks', function () {
    apiAs(User::factory()->tech()->create());
    Check::withoutEvents(fn () => Check::factory()->count(3)->create());

    $this->getJson('/api/v1/checks', apiAuth())->assertOk();

    DB::enableQueryLog();
    $this->getJson('/api/v1/checks', apiAuth())->assertOk();
    $few = count(DB::getQueryLog());

    Check::withoutEvents(fn () => Check::factory()->count(20)->create());
    DB::flushQueryLog();
    $this->getJson('/api/v1/checks?per_page=100', apiAuth())->assertOk()->assertJsonCount(23, 'data');

    expect(count(DB::getQueryLog()))->toBe($few);
});

it('documents every v1 route in docs/openapi.yaml', function () {
    $spec = Yaml::parseFile(base_path('docs/openapi.yaml'));

    foreach (Route::getRoutes() as $route) {
        if (! str_starts_with($route->getName() ?? '', 'api.v1.') && $route->getName() !== 'api.heartbeat') {
            continue;
        }

        $path = '/'.preg_replace(['#^api/v1/#', '#\{(check|group)\}#', '#\{outcome\?\}#'], ['', '{id}', 'fail'], $route->uri());
        $path = str_replace('/heartbeat/{token}/fail', '/heartbeat/{token}', $path);

        foreach (array_diff($route->methods(), ['HEAD', 'PUT']) as $method) {
            expect($spec['paths'])->toHaveKey($path);
            expect($spec['paths'][$path])->toHaveKey(strtolower($method));
        }
    }
});
