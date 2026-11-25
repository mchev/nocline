<?php

use App\Models\User;
use App\Modules\Hosts\Models\Host;

it('stores agent auto update preference on the host', function () {
    $user = User::factory()->tech()->create();
    $host = Host::factory()->enrolled()->create(['agent_auto_update' => false]);

    $this->actingAs($user)
        ->patch(route('hosts.update', $host), [
            'name' => $host->name,
            'agent_auto_update' => true,
        ])
        ->assertRedirect();

    expect($host->refresh()->agent_auto_update)->toBeTrue();
});
