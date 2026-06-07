<?php

use App\Models\Restaurant;

beforeEach(function () {
    $this->withHeader('Origin', config('app.frontend_url'));
});

it('favorites a published restaurant', function () {
    $user = actingAsClient();
    $restaurant = Restaurant::factory()->published()->create();

    $this->putJson("/api/restaurants/{$restaurant->id}/favorite")->assertNoContent();

    $this->assertDatabaseHas('favorites', [
        'user_id' => $user->id,
        'restaurant_id' => $restaurant->id,
    ]);
});

it('is idempotent when favoriting twice', function () {
    actingAsClient();
    $restaurant = Restaurant::factory()->published()->create();

    $this->putJson("/api/restaurants/{$restaurant->id}/favorite")->assertNoContent();
    $this->putJson("/api/restaurants/{$restaurant->id}/favorite")->assertNoContent();

    $this->assertDatabaseCount('favorites', 1);
});

it('cannot favorite a draft restaurant', function () {
    actingAsClient();
    $restaurant = Restaurant::factory()->create();

    $this->putJson("/api/restaurants/{$restaurant->id}/favorite")->assertNotFound();
    $this->assertDatabaseCount('favorites', 0);
});

it('unfavorites a restaurant', function () {
    $user = actingAsClient();
    $restaurant = Restaurant::factory()->published()->create();
    $user->favoriteRestaurants()->attach($restaurant->id);

    $this->deleteJson("/api/restaurants/{$restaurant->id}/favorite")->assertNoContent();

    $this->assertDatabaseMissing('favorites', [
        'user_id' => $user->id,
        'restaurant_id' => $restaurant->id,
    ]);
});

it('is idempotent when unfavoriting something not favorited', function () {
    actingAsClient();
    $restaurant = Restaurant::factory()->published()->create();

    $this->deleteJson("/api/restaurants/{$restaurant->id}/favorite")->assertNoContent();
});

it('lists the user favorites with is_favorited', function () {
    $user = actingAsClient();
    $favorites = Restaurant::factory()->count(2)->published()->create();
    $user->favoriteRestaurants()->attach($favorites->pluck('id'));
    Restaurant::factory()->published()->create();

    $this->getJson('/api/me/favorites')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.is_favorited', true);
});

it('excludes unpublished restaurants from the favorites list', function () {
    $user = actingAsClient();
    $published = Restaurant::factory()->published()->create();
    $draft = Restaurant::factory()->create();
    $user->favoriteRestaurants()->attach([$published->id, $draft->id]);

    $this->getJson('/api/me/favorites')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $published->id);
});

it('excludes soft-deleted restaurants but preserves the favorite', function () {
    $user = actingAsClient();
    $restaurant = Restaurant::factory()->published()->create();
    $user->favoriteRestaurants()->attach($restaurant->id);

    $restaurant->delete();

    $this->getJson('/api/me/favorites')
        ->assertOk()
        ->assertJsonCount(0, 'data');

    $this->assertDatabaseHas('favorites', [
        'user_id' => $user->id,
        'restaurant_id' => $restaurant->id,
    ]);
});

it('requires authentication to favorite', function () {
    $restaurant = Restaurant::factory()->published()->create();

    $this->putJson("/api/restaurants/{$restaurant->id}/favorite")->assertUnauthorized();
});
