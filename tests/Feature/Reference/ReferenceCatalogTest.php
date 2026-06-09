<?php

use App\Models\Allergen;
use App\Models\CuisineType;

test('an authenticated restaurateur can list active cuisine types', function (): void {
    actingAsRestaurateur();
    CuisineType::factory()->count(2)->create(['is_active' => true]);
    CuisineType::factory()->create(['is_active' => false]);

    $this->getJson('/api/cuisine-types')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonStructure(['data' => [['id', 'name', 'is_active']]]);
});

test('an authenticated restaurateur can list allergens', function (): void {
    actingAsRestaurateur();
    Allergen::factory()->count(3)->create();

    $this->getJson('/api/allergens')
        ->assertOk()
        ->assertJsonCount(3, 'data')
        ->assertJsonStructure(['data' => [['id', 'name', 'icon', 'position']]]);
});

test('guests cannot list reference catalogues', function (): void {
    $this->getJson('/api/cuisine-types')->assertUnauthorized();
    $this->getJson('/api/allergens')->assertUnauthorized();
});
