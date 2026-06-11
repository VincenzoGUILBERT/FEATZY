<?php

beforeEach(function () {
    $this->withHeader('Origin', config('app.frontend_url'));
});

it('requires authentication to update', function () {
    $this->putJson('/api/me/dietary-preferences', ['dietary_preferences' => []])
        ->assertUnauthorized();
});

it('lists the available dietary preferences', function () {
    actingAsClient();

    $this->getJson('/api/dietary-preferences')
        ->assertOk()
        ->assertJsonPath('data.0.value', 'vegetarian')
        ->assertJsonPath('data.0.label', 'Végétarien');
});

it('updates the user dietary preferences', function () {
    $user = actingAsClient();

    $this->putJson('/api/me/dietary-preferences', [
        'dietary_preferences' => ['vegan', 'halal'],
    ])
        ->assertOk()
        ->assertJsonPath('data.dietary_preferences', ['vegan', 'halal']);

    expect($user->fresh()->dietary_preferences)->toBe(['vegan', 'halal']);
});

it('clears the preferences with an empty array', function () {
    actingAsClient(['dietary_preferences' => ['vegan']]);

    $this->putJson('/api/me/dietary-preferences', ['dietary_preferences' => []])
        ->assertOk()
        ->assertJsonPath('data.dietary_preferences', []);
});

it('rejects unknown preferences', function () {
    actingAsClient();

    $this->putJson('/api/me/dietary-preferences', [
        'dietary_preferences' => ['banana'],
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('dietary_preferences.0');
});

it('exposes dietary_preferences on the user resource', function () {
    actingAsClient(['dietary_preferences' => ['keto']]);

    $this->getJson('/api/user')
        ->assertOk()
        ->assertJsonPath('data.dietary_preferences', ['keto']);
});
