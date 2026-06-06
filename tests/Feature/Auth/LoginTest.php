<?php

use App\Models\User;

beforeEach(function () {
    // Mimic a first-party request coming from the SPA so that
    // EnsureFrontendRequestsAreStateful starts the session (required for the
    // session-based web guard login). The Origin must match a stateful domain,
    // i.e. the SPA URL — not the API's own app.url.
    $this->withHeader('Origin', config('app.frontend_url'));
});

it('logs in a user with valid credentials', function () {
    $user = User::factory()->create([
        'email' => 'jane@example.com',
        'password' => 'password',
    ]);

    $this->postJson('/api/login', [
        'email' => 'jane@example.com',
        'password' => 'password',
    ])
        ->assertOk()
        ->assertJsonPath('data.id', $user->id)
        ->assertJsonPath('data.email', 'jane@example.com');

    $this->assertAuthenticatedAs($user);
});

it('rejects invalid credentials', function () {
    User::factory()->create(['email' => 'jane@example.com']);

    $this->postJson('/api/login', [
        'email' => 'jane@example.com',
        'password' => 'wrong-password',
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('email');

    $this->assertGuest();
});

it('requires email and password', function () {
    $this->postJson('/api/login', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['email', 'password']);
});
