<?php

use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->withHeader('Origin', config('app.frontend_url'));
});

it('changes the password with the correct current password', function () {
    $user = actingAsClient();

    $this->putJson('/api/me/password', [
        'current_password' => 'password',
        'password' => 'NewPassword123!',
        'password_confirmation' => 'NewPassword123!',
    ])->assertNoContent();

    expect(Hash::check('NewPassword123!', $user->fresh()->password))->toBeTrue();
});

it('rejects a wrong current password', function () {
    actingAsClient();

    $this->putJson('/api/me/password', [
        'current_password' => 'wrong-password',
        'password' => 'NewPassword123!',
        'password_confirmation' => 'NewPassword123!',
    ])->assertStatus(422)->assertJsonValidationErrors('current_password');
});

it('rejects a new password identical to the current one', function () {
    actingAsClient();

    $this->putJson('/api/me/password', [
        'current_password' => 'password',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertStatus(422)->assertJsonValidationErrors('password');
});

it('requires the new password to be confirmed', function () {
    actingAsClient();

    $this->putJson('/api/me/password', [
        'current_password' => 'password',
        'password' => 'NewPassword123!',
        'password_confirmation' => 'Mismatch123!',
    ])->assertStatus(422)->assertJsonValidationErrors('password');
});

it('rejects password change for guests', function () {
    $this->putJson('/api/me/password', [])->assertUnauthorized();
});
