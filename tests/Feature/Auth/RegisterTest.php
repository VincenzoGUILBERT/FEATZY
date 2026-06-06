<?php

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

it('registers a client account and sends the verification email', function () {
    Notification::fake();

    $response = $this->postJson('/api/register', [
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'email' => 'jane@example.com',
        'phone' => '+33612345678',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.email', 'jane@example.com')
        ->assertJsonPath('data.roles', ['client']);

    $user = User::whereEmail('jane@example.com')->firstOrFail();

    expect($user->hasRole('client'))->toBeTrue();
    expect($user->hasVerifiedEmail())->toBeFalse();

    Notification::assertSentTo($user, VerifyEmail::class);
});

it('does not log the user in after registration', function () {
    Notification::fake();

    $this->postJson('/api/register', [
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'email' => 'jane@example.com',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
    ])->assertCreated();

    $this->assertGuest();
});

it('requires the mandatory fields', function () {
    $this->postJson('/api/register', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['first_name', 'last_name', 'email', 'password']);
});

it('rejects a duplicate email', function () {
    User::factory()->create(['email' => 'jane@example.com']);

    $this->postJson('/api/register', [
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'email' => 'jane@example.com',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
    ])->assertStatus(422)->assertJsonValidationErrors('email');
});

it('rejects a password without confirmation', function () {
    $this->postJson('/api/register', [
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'email' => 'jane@example.com',
        'password' => 'Password123!',
        'password_confirmation' => 'different',
    ])->assertStatus(422)->assertJsonValidationErrors('password');
});

it('lets a freshly registered user log in (password is hashed once)', function () {
    Notification::fake();
    $this->withHeader('Origin', config('app.frontend_url'));

    $this->postJson('/api/register', [
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'email' => 'jane@example.com',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
    ])->assertCreated();

    $this->postJson('/api/login', [
        'email' => 'jane@example.com',
        'password' => 'Password123!',
    ])->assertOk();

    $this->assertAuthenticated();
});
