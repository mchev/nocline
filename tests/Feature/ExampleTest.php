<?php

use App\Models\User;

test('guests are sent to login from home', function () {
    $this->get(route('home'))->assertRedirect(route('login'));
});

test('authenticated users are sent to the dashboard from home', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('home'))
        ->assertRedirect(route('dashboard'));
});
