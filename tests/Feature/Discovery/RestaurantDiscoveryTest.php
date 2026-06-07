<?php

use App\Models\CuisineType;
use App\Models\Restaurant;

beforeEach(function () {
    $this->withHeader('Origin', config('app.frontend_url'));
});

it('lists only published restaurants', function () {
    Restaurant::factory()->count(2)->published()->create();
    Restaurant::factory()->count(3)->create();

    $this->getJson('/api/discovery/restaurants')
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

it('shows a published restaurant', function () {
    $restaurant = Restaurant::factory()->published()->create();

    $this->getJson("/api/discovery/restaurants/{$restaurant->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $restaurant->id);
});

it('returns 404 for a draft restaurant detail', function () {
    $restaurant = Restaurant::factory()->create();

    $this->getJson("/api/discovery/restaurants/{$restaurant->id}")->assertNotFound();
});

it('filters by city', function () {
    Restaurant::factory()->published()->create(['city' => 'Lyon']);
    Restaurant::factory()->published()->create(['city' => 'Paris']);

    $this->getJson('/api/discovery/restaurants?filter[city]=Lyon')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.address.city', 'Lyon');
});

it('filters by price level', function () {
    Restaurant::factory()->published()->create(['price_level' => 1]);
    Restaurant::factory()->published()->create(['price_level' => 3]);

    $this->getJson('/api/discovery/restaurants?filter[price_level]=3')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.price_level', 3);
});

it('filters by cuisine type', function () {
    $cuisine = CuisineType::factory()->create();
    $matching = Restaurant::factory()->published()->create();
    $matching->cuisineTypes()->attach($cuisine);
    Restaurant::factory()->published()->create();

    $this->getJson("/api/discovery/restaurants?filter[cuisine]={$cuisine->id}")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $matching->id)
        // The admin-only usage count must never leak into the public payload.
        ->assertJsonMissingPath('data.0.cuisine_types.0.restaurants_count');
});

it('filters by minimum rating', function () {
    Restaurant::factory()->published()->create(['average_rating' => 4.5]);
    Restaurant::factory()->published()->create(['average_rating' => 2.0]);

    $this->getJson('/api/discovery/restaurants?filter[min_rating]=4')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('filters by accepts_preorders', function () {
    Restaurant::factory()->published()->create(['accepts_preorders' => true]);
    Restaurant::factory()->published()->create(['accepts_preorders' => false]);

    $this->getJson('/api/discovery/restaurants?filter[accepts_preorders]=1')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('searches by full text', function () {
    Restaurant::factory()->published()->create(['name' => 'Sushi Paradise', 'description' => 'Fresh sashimi']);
    Restaurant::factory()->published()->create(['name' => 'Pizza Corner', 'description' => 'Wood fired']);

    $this->getJson('/api/discovery/restaurants?filter[search]=Sushi')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Sushi Paradise');
});

it('sorts nearest first when coordinates are provided', function () {
    $near = Restaurant::factory()->published()->create(['latitude' => 48.8566, 'longitude' => 2.3522]);
    $far = Restaurant::factory()->published()->create(['latitude' => 48.9500, 'longitude' => 2.5000]);

    $this->getJson('/api/discovery/restaurants?latitude=48.8566&longitude=2.3522')
        ->assertOk()
        ->assertJsonPath('data.0.id', $near->id)
        ->assertJsonPath('data.1.id', $far->id);
});

it('restricts results to the given radius', function () {
    $near = Restaurant::factory()->published()->create(['latitude' => 48.8566, 'longitude' => 2.3522]);
    Restaurant::factory()->published()->create(['latitude' => 48.9500, 'longitude' => 2.5000]);

    $this->getJson('/api/discovery/restaurants?latitude=48.8566&longitude=2.3522&radius=3')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $near->id);
});

it('rejects invalid coordinates', function () {
    $this->getJson('/api/discovery/restaurants?latitude=200&longitude=2.35')
        ->assertStatus(422)
        ->assertJsonValidationErrors('latitude');
});

it('exposes is_favorited for an authenticated user', function () {
    $user = actingAsClient();
    $favorited = Restaurant::factory()->published()->create();
    $user->favoriteRestaurants()->attach($favorited->id);

    $this->getJson("/api/discovery/restaurants/{$favorited->id}")
        ->assertOk()
        ->assertJsonPath('data.is_favorited', true);
});

it('omits is_favorited for anonymous visitors', function () {
    $restaurant = Restaurant::factory()->published()->create();

    $this->getJson("/api/discovery/restaurants/{$restaurant->id}")
        ->assertOk()
        ->assertJsonMissingPath('data.is_favorited');
});
