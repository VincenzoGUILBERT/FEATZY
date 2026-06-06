<?php

use App\Models\User;

beforeEach(function () {
    // The Origin must match a stateful domain (the SPA), not the API's app.url,
    // otherwise no session is started and the web guard cannot resolve.
    $this->withHeader('Origin', config('app.frontend_url'));
});

it('logs the authenticated user out', function () {
    $user = User::factory()->create();

    $this->actingAs($user, 'web')
        ->postJson('/api/logout')
        ->assertNoContent();

    // The web (session) guard is cleared by logout(). We assert on it explicitly
    // because auth:sanctum switches the default guard to the request guard, whose
    // per-request resolved user is still cached within the same test process.
    $this->assertGuest('web');
});

it('rejects logout for guests', function () {
    $this->postJson('/api/logout')->assertUnauthorized();
});
