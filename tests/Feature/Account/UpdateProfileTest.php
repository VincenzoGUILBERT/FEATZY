<?php

beforeEach(function () {
    $this->withHeader('Origin', config('app.frontend_url'));
});

it('updates all provided profile fields', function () {
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

it('updates only the provided fields and leaves the others untouched', function () {
    $user = actingAsClient(['first_name' => 'Jane', 'last_name' => 'Doe', 'phone' => '+33600000000']);

    $this->patchJson('/api/me', ['first_name' => 'Janet'])
        ->assertOk()
        ->assertJsonPath('data.first_name', 'Janet')
        ->assertJsonPath('data.last_name', 'Doe')
        ->assertJsonPath('data.phone', '+33600000000');

    $fresh = $user->fresh();
    expect($fresh->last_name)->toBe('Doe');
    expect($fresh->phone)->toBe('+33600000000');
});

it('clears the phone when explicitly set to null', function () {
    $user = actingAsClient(['phone' => '+33600000000']);

    $this->patchJson('/api/me', ['phone' => null])
        ->assertOk()
        ->assertJsonPath('data.phone', null);

    expect($user->fresh()->phone)->toBeNull();
});

it('rejects empty values for fields that are present', function () {
    actingAsClient();

    $this->patchJson('/api/me', ['first_name' => '', 'last_name' => ''])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['first_name', 'last_name']);
});

it('accepts an empty payload as a no-op', function () {
    $user = actingAsClient(['first_name' => 'Jane']);

    $this->patchJson('/api/me', [])
        ->assertOk()
        ->assertJsonPath('data.first_name', 'Jane');
});

it('rejects profile updates for guests', function () {
    $this->patchJson('/api/me', [
        'first_name' => 'New',
    ])->assertUnauthorized();
});
