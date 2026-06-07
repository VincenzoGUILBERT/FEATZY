<?php

use App\Models\Restaurant;

beforeEach(function () {
    $this->withHeader('Origin', config('app.frontend_url'));
});

it('lists only the restaurateur own restaurants', function () {
    $owner = actingAsRestaurateur();
    Restaurant::factory()->count(2)->for($owner, 'owner')->create();
    Restaurant::factory()->count(3)->create();

    $this->getJson('/api/me/restaurants')
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

it('shows an owned restaurant', function () {
    $owner = actingAsRestaurateur();
    $restaurant = Restaurant::factory()->for($owner, 'owner')->create();

    $this->getJson("/api/restaurants/{$restaurant->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $restaurant->id);
});

it('forbids viewing a restaurant owned by someone else', function () {
    actingAsRestaurateur();
    $other = Restaurant::factory()->create();

    $this->getJson("/api/restaurants/{$other->id}")->assertForbidden();
});

it('publishes an owned draft restaurant', function () {
    $owner = actingAsRestaurateur();
    $restaurant = Restaurant::factory()->for($owner, 'owner')->create();

    $this->postJson("/api/restaurants/{$restaurant->id}/publish")
        ->assertOk()
        ->assertJsonPath('data.status', 'published');

    expect($restaurant->fresh()->status->value)->toBe('published');
});

it('soft-deletes an owned restaurant', function () {
    $owner = actingAsRestaurateur();
    $restaurant = Restaurant::factory()->for($owner, 'owner')->create();

    $this->deleteJson("/api/restaurants/{$restaurant->id}")->assertNoContent();

    $this->assertSoftDeleted($restaurant);
});

it('forbids deleting a restaurant owned by someone else', function () {
    actingAsRestaurateur();
    $other = Restaurant::factory()->create();

    $this->deleteJson("/api/restaurants/{$other->id}")->assertForbidden();
});

it('lets an admin view any restaurant via the gate bypass', function () {
    actingAsAdmin();
    $restaurant = Restaurant::factory()->create();

    $this->getJson("/api/restaurants/{$restaurant->id}")->assertOk();
});
