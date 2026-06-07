<?php

use App\Models\CuisineType;
use App\Models\Restaurant;

beforeEach(function () {
    $this->withHeader('Origin', config('app.frontend_url'));
});

it('updates only the provided fields', function () {
    $owner = actingAsRestaurateur();
    $restaurant = Restaurant::factory()->for($owner, 'owner')->create([
        'name' => 'Old',
        'city' => 'Lyon',
    ]);

    $this->patchJson("/api/restaurants/{$restaurant->id}", ['name' => 'New'])
        ->assertOk()
        ->assertJsonPath('data.name', 'New')
        ->assertJsonPath('data.address.city', 'Lyon');

    expect($restaurant->fresh()->name)->toBe('New');
});

it('syncs the cuisine types', function () {
    $owner = actingAsRestaurateur();
    $restaurant = Restaurant::factory()->for($owner, 'owner')->create();
    $a = CuisineType::factory()->create();
    $b = CuisineType::factory()->create();
    $restaurant->cuisineTypes()->attach($a);

    $this->patchJson("/api/restaurants/{$restaurant->id}", [
        'cuisine_type_ids' => [$b->id],
    ])
        ->assertOk()
        ->assertJsonCount(1, 'data.cuisine_types')
        ->assertJsonPath('data.cuisine_types.0.id', $b->id);

    expect($restaurant->cuisineTypes()->pluck('cuisine_types.id')->all())->toBe([$b->id]);
});

it('forbids updating a restaurant owned by someone else', function () {
    actingAsRestaurateur();
    $other = Restaurant::factory()->create();

    $this->patchJson("/api/restaurants/{$other->id}", ['name' => 'Hack'])
        ->assertForbidden();

    expect($other->fresh()->name)->not->toBe('Hack');
});
