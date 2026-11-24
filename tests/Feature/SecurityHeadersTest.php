<?php

use App\Models\User;

it('sends hardening headers on app pages and forbids foreign framing', function () {
    $response = $this->actingAs(User::factory()->create())->get(route('dashboard'));

    $response->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
        ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
        ->assertHeader('Permissions-Policy');
});

it('lets wallboards and public status pages be framed', function () {
    $this->get('/wallboard/does-not-exist')->assertHeader('X-Content-Type-Options', 'nosniff')->assertHeaderMissing('X-Frame-Options');
    $this->get('/status/does-not-exist')->assertHeaderMissing('X-Frame-Options');
    $this->actingAs(User::factory()->create())->get('/status-pages')->assertHeader('X-Frame-Options', 'SAMEORIGIN');
});

it('only sends HSTS over HTTPS', function () {
    $this->get('/login')->assertHeaderMissing('Strict-Transport-Security');
    $this->get('https://localhost/login')->assertHeader('Strict-Transport-Security');
});
