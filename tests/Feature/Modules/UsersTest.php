<?php

use App\Enums\UserRole;
use App\Models\User;

it('has no public registration', function () {
    $this->get('/register')->assertNotFound();
});

it('restricts user management to admins', function () {
    $this->actingAs(User::factory()->tech()->create())->get(route('users.index'))->assertForbidden();
    $this->actingAs(User::factory()->admin()->create())->get(route('users.index'))->assertOk();
});

it('lets an admin create, re-role and delete users but not themselves', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    $this->post(route('users.store'), [
        'name' => 'Tech', 'email' => 'tech@example.com', 'role' => 'tech', 'password' => 'Str0ng!Passw0rd',
    ])->assertRedirect();

    $tech = User::where('email', 'tech@example.com')->firstOrFail();
    expect($tech->role)->toBe(UserRole::Tech)->and($tech->email_verified_at)->not->toBeNull();

    $this->patch(route('users.update', $tech), ['role' => 'viewer'])->assertRedirect();
    expect($tech->refresh()->role)->toBe(UserRole::Viewer);

    $this->patch(route('users.update', $admin), ['role' => 'viewer'])->assertForbidden();
    $this->delete(route('users.destroy', $admin))->assertForbidden();
    $this->delete(route('users.destroy', $tech))->assertRedirect();
    expect(User::count())->toBe(1);
});

it('rejects weak passwords and duplicate emails', function () {
    User::factory()->create(['email' => 'dup@example.com']);
    $this->actingAs(User::factory()->admin()->create());

    $this->post(route('users.store'), ['name' => 'A', 'email' => 'dup@example.com', 'role' => 'viewer', 'password' => 'weak'])
        ->assertSessionHasErrors(['email', 'password']);
});
