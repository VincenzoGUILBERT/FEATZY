<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

it('sends a reset link for an existing email and returns 200', function () {
    Notification::fake();

    $user = User::factory()->create(['email' => 'jane@example.com']);

    $this->postJson('/api/forgot-password', [
        'email' => 'jane@example.com',
    ])->assertOk()->assertJsonStructure(['message']);

    Notification::assertSentTo($user, ResetPassword::class);
});

it('returns a generic 200 and sends nothing for an unknown email (enumeration safe)', function () {
    Notification::fake();

    $this->postJson('/api/forgot-password', [
        'email' => 'nobody@example.com',
    ])->assertOk()->assertJsonStructure(['message']);

    Notification::assertNothingSent();
});

it('validates the email field on forgot password', function () {
    $this->postJson('/api/forgot-password', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors('email');
});

it('resets the password with a valid token and returns 200', function () {
    $user = User::factory()->create(['email' => 'jane@example.com']);

    $token = Password::createToken($user);

    $this->postJson('/api/reset-password', [
        'token' => $token,
        'email' => 'jane@example.com',
        'password' => 'NewPassword123!',
        'password_confirmation' => 'NewPassword123!',
    ])->assertOk()->assertJsonStructure(['message']);

    $fresh = $user->fresh();

    expect(Hash::check('NewPassword123!', $fresh->password))->toBeTrue();
    expect(Hash::check('password', $fresh->password))->toBeFalse();
});

it('lets the user log in with the new password after a reset', function () {
    $this->withHeader('Origin', config('app.frontend_url'));

    $user = User::factory()->create(['email' => 'jane@example.com']);

    $token = Password::createToken($user);

    $this->postJson('/api/reset-password', [
        'token' => $token,
        'email' => 'jane@example.com',
        'password' => 'NewPassword123!',
        'password_confirmation' => 'NewPassword123!',
    ])->assertOk();

    $this->postJson('/api/login', [
        'email' => 'jane@example.com',
        'password' => 'NewPassword123!',
    ])->assertOk();

    $this->assertAuthenticatedAs($user->fresh());
});

it('rejects a reset with an invalid token', function () {
    User::factory()->create(['email' => 'jane@example.com']);

    $this->postJson('/api/reset-password', [
        'token' => 'invalid-token',
        'email' => 'jane@example.com',
        'password' => 'NewPassword123!',
        'password_confirmation' => 'NewPassword123!',
    ])->assertStatus(422)->assertJsonValidationErrors('email');
});

it('rejects a reset when the email does not match the token owner', function () {
    $user = User::factory()->create(['email' => 'jane@example.com']);
    User::factory()->create(['email' => 'john@example.com']);

    $token = Password::createToken($user);

    $this->postJson('/api/reset-password', [
        'token' => $token,
        'email' => 'john@example.com',
        'password' => 'NewPassword123!',
        'password_confirmation' => 'NewPassword123!',
    ])->assertStatus(422)->assertJsonValidationErrors('email');
});

it('requires a matching password confirmation on reset', function () {
    $user = User::factory()->create(['email' => 'jane@example.com']);

    $token = Password::createToken($user);

    $this->postJson('/api/reset-password', [
        'token' => $token,
        'email' => 'jane@example.com',
        'password' => 'NewPassword123!',
        'password_confirmation' => 'different',
    ])->assertStatus(422)->assertJsonValidationErrors('password');
});

it('requires the mandatory fields on reset', function () {
    $this->postJson('/api/reset-password', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['token', 'email', 'password']);
});
