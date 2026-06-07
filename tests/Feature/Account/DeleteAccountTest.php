<?php

use App\Models\User;

beforeEach(function () {
    $this->withHeader('Origin', config('app.frontend_url'));
});

it('soft-deletes the account with the correct password', function () {
    $user = actingAsClient();

    $this->deleteJson('/api/me', [
        'password' => 'password',
    ])->assertNoContent();

    expect(User::find($user->id))->toBeNull();
    expect(User::withTrashed()->find($user->id))->not->toBeNull();

    // auth:sanctum makes sanctum the default guard, whose per-request resolved
    // user stays cached in-process; logout() clears the web guard, so assert on it.
    $this->assertGuest('web');
});

it('rejects deletion with a wrong password', function () {
    $user = actingAsClient();

    $this->deleteJson('/api/me', [
        'password' => 'nope',
    ])->assertStatus(422)->assertJsonValidationErrors('password');

    expect(User::find($user->id))->not->toBeNull();
});

it('rejects account deletion for guests', function () {
    $this->deleteJson('/api/me', [])->assertUnauthorized();
});
