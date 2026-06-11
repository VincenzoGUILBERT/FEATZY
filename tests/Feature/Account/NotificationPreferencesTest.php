<?php

beforeEach(function () {
    $this->withHeader('Origin', config('app.frontend_url'));
});

it('requires authentication', function () {
    $this->putJson('/api/me/notification-preferences', ['email' => false])
        ->assertUnauthorized();
});

it('defaults every channel to true on the user resource', function () {
    actingAsClient();

    $this->getJson('/api/user')
        ->assertOk()
        ->assertJsonPath('data.notification_preferences.email', true)
        ->assertJsonPath('data.notification_preferences.push', true)
        ->assertJsonPath('data.notification_preferences.important_updates', true)
        ->assertJsonPath('data.notification_preferences.promotions', true);
});

it('updates a single channel and keeps the others', function () {
    $user = actingAsClient();

    $this->putJson('/api/me/notification-preferences', ['promotions' => false])
        ->assertOk()
        ->assertJsonPath('data.notification_preferences.promotions', false)
        ->assertJsonPath('data.notification_preferences.email', true);

    expect($user->fresh()->notificationPreferences())
        ->toMatchArray(['promotions' => false, 'email' => true]);
});

it('rejects a non-boolean value', function () {
    actingAsClient();

    $this->putJson('/api/me/notification-preferences', ['email' => 'maybe'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('email');
});
