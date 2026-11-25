<?php

use App\Models\User;
use App\Modules\Hosts\Models\Host;

it('updates agent auto update without host updated flash toast', function () {
    $user = User::factory()->tech()->create();
    $host = Host::factory()->enrolled()->create(['agent_auto_update' => true]);

    $response = $this->actingAs($user)
        ->patch(route('hosts.update', $host), [
            'name' => $host->name,
            'agent_auto_update' => false,
        ]);

    $response->assertRedirect();
    expect($host->refresh()->agent_auto_update)->toBeFalse();

    $flash = session('flash');
    expect($flash)->toBeNull();
});

it('still updates other host fields in the same request shape', function () {
    $user = User::factory()->tech()->create();
    $host = Host::factory()->enrolled()->create();

    $this->actingAs($user)
        ->patch(route('hosts.update', $host), [
            'name' => 'Renamed server',
            'tags' => ['prod'],
        ])
        ->assertRedirect();

    expect($host->refresh()->name)->toBe('Renamed server')
        ->and($host->tags)->toBe(['prod']);
});
