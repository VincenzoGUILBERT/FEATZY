<?php

use App\Models\User;

beforeEach(function () {
    $this->withHeader('Origin', config('app.frontend_url'));
});

it('requires authentication', function () {
    $this->getJson('/api/users/search?q=jeanne')->assertUnauthorized();
});

it('validates the query length', function () {
    actingAsClient();

    $this->getJson('/api/users/search?q=a')
        ->assertStatus(422)
        ->assertJsonValidationErrors('q');
});

it('finds users by name and returns identity only', function () {
    actingAsClient();
    $match = User::factory()->create(['first_name' => 'Jeanne', 'last_name' => 'Martin']);
    User::factory()->create(['first_name' => 'Paul']);

    $this->getJson('/api/users/search?q=Jeanne')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $match->id)
        ->assertJsonMissingPath('data.0.email')
        ->assertJsonMissingPath('data.0.phone');
});

it('finds users by email', function () {
    actingAsClient();
    $match = User::factory()->create(['email' => 'target@featzy.test']);

    $this->getJson('/api/users/search?q=target@featzy')
        ->assertOk()
        ->assertJsonPath('data.0.id', $match->id);
});

it('excludes the current user from results', function () {
    actingAsClient(['first_name' => 'Jeanne', 'last_name' => 'Searcher']);
    $other = User::factory()->create(['first_name' => 'Jeanne', 'last_name' => 'Other']);

    $this->getJson('/api/users/search?q=Jeanne')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $other->id);
});
