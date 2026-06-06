<?php

use App\Models\User;

it('returns the authenticated user', function () {
    $user = User::factory()->create(['email' => 'jane@example.com']);

    $this->actingAs($user)
        ->getJson('/api/user')
        ->assertOk()
        ->assertJsonPath('data.id', $user->id)
        ->assertJsonPath('data.email', 'jane@example.com');
});

it('rejects unauthenticated access', function () {
    $this->getJson('/api/user')->assertUnauthorized();
});
