<?php

beforeEach(function () {
    $this->withHeader('Origin', config('app.frontend_url'));
});

it('updates the authenticated user profile', function () {
    $user = actingAsClient(['first_name' => 'Old', 'last_name' => 'Name']);

    $this->patchJson('/api/me', [
        'first_name' => 'New',
        'last_name' => 'Person',
        'phone' => '+33611223344',
    ])
        ->assertOk()
        ->assertJsonPath('data.first_name', 'New')
        ->assertJsonPath('data.last_name', 'Person')
        ->assertJsonPath('data.phone', '+33611223344')
        ->assertJsonPath('data.roles', ['client']);

    expect($user->fresh()->first_name)->toBe('New');
});

it('clears an optional phone when omitted', function () {
    $user = actingAsClient(['phone' => '+33600000000']);

    $this->patchJson('/api/me', [
        'first_name' => 'Jane',
        'last_name' => 'Doe',
    ])->assertOk()->assertJsonPath('data.phone', null);

    expect($user->fresh()->phone)->toBeNull();
});

it('requires the mandatory profile fields', function () {
    actingAsClient();

    $this->patchJson('/api/me', ['first_name' => '', 'last_name' => ''])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['first_name', 'last_name']);
});

it('rejects profile updates for guests', function () {
    $this->patchJson('/api/me', [
        'first_name' => 'New',
        'last_name' => 'Person',
    ])->assertUnauthorized();
});
