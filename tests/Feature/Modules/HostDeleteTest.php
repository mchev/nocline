<?php

use App\Models\User;
use App\Modules\Hosts\Models\Host;

it('allows admin to delete a host and redirects to index', function () {
    $admin = User::factory()->admin()->create();
    $host = Host::factory()->enrolled()->create(['name' => 'gone']);

    $this->actingAs($admin)
        ->delete(route('hosts.destroy', $host))
        ->assertRedirect(route('hosts.index'));

    expect(Host::query()->whereKey($host->id)->exists())->toBeFalse();
});

it('forbids non-admin from deleting a host', function () {
    $tech = User::factory()->tech()->create();
    $host = Host::factory()->create();

    $this->actingAs($tech)
        ->delete(route('hosts.destroy', $host))
        ->assertForbidden();
});
