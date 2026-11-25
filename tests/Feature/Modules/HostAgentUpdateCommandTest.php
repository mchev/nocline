<?php

use App\Models\User;
use App\Modules\Hosts\Models\Host;

it('returns a curl install line to refresh an enrolled agent', function () {
    $user = User::factory()->tech()->create();
    $host = Host::factory()->create(['enrolled_at' => now(), 'agent_version' => '0.1.0-demo']);

    $response = $this->actingAs($user)->postJson(route('hosts.agent-update-command', $host));

    $response->assertOk()
        ->assertJsonStructure(['command', 'hub_version'])
        ->assertJsonPath('hub_version', (string) config('nocline.version'));

    expect($response->json('command'))->toContain('/install/agent-update.sh');
});

it('forbids viewers from requesting an agent update command', function () {
    $user = User::factory()->create(['role' => 'viewer']);
    $host = Host::factory()->create(['enrolled_at' => now()]);

    $this->actingAs($user)
        ->postJson(route('hosts.agent-update-command', $host))
        ->assertForbidden();
});

it('rejects agent update command when the host is not enrolled', function () {
    $user = User::factory()->tech()->create();
    $host = Host::factory()->create(['enrolled_at' => null]);

    $this->actingAs($user)
        ->postJson(route('hosts.agent-update-command', $host))
        ->assertStatus(422);
});
