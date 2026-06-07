<?php

use App\Models\CuisineType;

beforeEach(function () {
    $this->withHeader('Origin', config('app.frontend_url'));
});

it('lets a restaurateur create a restaurant as draft', function () {
    $owner = actingAsRestaurateur();

    $this->postJson('/api/restaurants', [
        'name' => 'Chez Featzy',
        'city' => 'Paris',
        'price_level' => 2,
        'accepts_preorders' => true,
    ])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Chez Featzy')
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonPath('data.owner_id', $owner->id)
        ->assertJsonPath('data.price_level', 2)
        ->assertJsonPath('data.accepts_preorders', true)
        ->assertJsonPath('data.accepts_online_payment', false)
        ->assertJsonPath('data.reviews_count', 0);

    $this->assertDatabaseHas('restaurants', [
        'name' => 'Chez Featzy',
        'owner_id' => $owner->id,
        'status' => 'draft',
    ]);
});

it('attaches active cuisine types on creation', function () {
    actingAsRestaurateur();
    $cuisines = CuisineType::factory()->count(2)->create();

    $this->postJson('/api/restaurants', [
        'name' => 'Le Gourmet',
        'cuisine_type_ids' => $cuisines->pluck('id')->all(),
    ])
        ->assertCreated()
        ->assertJsonCount(2, 'data.cuisine_types');
});

it('rejects an inactive or unknown cuisine type', function () {
    actingAsRestaurateur();
    $inactive = CuisineType::factory()->inactive()->create();

    $this->postJson('/api/restaurants', [
        'name' => 'Le Gourmet',
        'cuisine_type_ids' => [$inactive->id, 99999],
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['cuisine_type_ids.0', 'cuisine_type_ids.1']);
});

it('requires a name', function () {
    actingAsRestaurateur();

    $this->postJson('/api/restaurants', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors('name');
});

it('validates the price level range', function () {
    actingAsRestaurateur();

    $this->postJson('/api/restaurants', ['name' => 'X', 'price_level' => 9])
        ->assertStatus(422)
        ->assertJsonValidationErrors('price_level');
});

it('requires latitude and longitude together', function () {
    actingAsRestaurateur();

    $this->postJson('/api/restaurants', ['name' => 'X', 'latitude' => 48.85])
        ->assertStatus(422)
        ->assertJsonValidationErrors('longitude');
});

it('forbids clients from creating restaurants', function () {
    actingAsClient();

    $this->postJson('/api/restaurants', ['name' => 'X'])
        ->assertForbidden();
});

it('requires authentication', function () {
    $this->postJson('/api/restaurants', ['name' => 'X'])
        ->assertUnauthorized();
});
