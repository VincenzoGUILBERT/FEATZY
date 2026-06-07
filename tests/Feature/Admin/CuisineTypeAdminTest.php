<?php

use App\Models\CuisineType;
use App\Models\Restaurant;

test('an admin can list cuisine types with their usage count', function (): void {
    actingAsAdmin();
    CuisineType::factory()->count(2)->create();

    $this->getJson('/api/admin/cuisine-types')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonStructure(['data' => [['id', 'name', 'is_active', 'restaurants_count']]]);
});

test('an admin can create a cuisine type', function (): void {
    actingAsAdmin();

    $this->postJson('/api/admin/cuisine-types', ['name' => 'Italienne'])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Italienne')
        ->assertJsonPath('data.is_active', true);

    $this->assertDatabaseHas('cuisine_types', ['name' => 'Italienne']);
});

test('a cuisine type name must be unique on create', function (): void {
    actingAsAdmin();
    CuisineType::factory()->create(['name' => 'Italienne']);

    $this->postJson('/api/admin/cuisine-types', ['name' => 'Italienne'])
        ->assertUnprocessable()->assertJsonValidationErrorFor('name');
});

test('a cuisine type name is required', function (): void {
    actingAsAdmin();

    $this->postJson('/api/admin/cuisine-types', [])
        ->assertUnprocessable()->assertJsonValidationErrorFor('name');
});

test('an admin can update a cuisine type, ignoring its own name in the unique check', function (): void {
    actingAsAdmin();
    $cuisineType = CuisineType::factory()->create(['name' => 'Italienne', 'is_active' => true]);

    $this->patchJson("/api/admin/cuisine-types/{$cuisineType->id}", ['name' => 'Italienne', 'is_active' => false])
        ->assertOk()
        ->assertJsonPath('data.is_active', false);

    $this->assertDatabaseHas('cuisine_types', ['id' => $cuisineType->id, 'is_active' => false]);
});

test('an admin can delete an unused cuisine type', function (): void {
    actingAsAdmin();
    $cuisineType = CuisineType::factory()->create();

    $this->deleteJson("/api/admin/cuisine-types/{$cuisineType->id}")->assertNoContent();

    $this->assertDatabaseMissing('cuisine_types', ['id' => $cuisineType->id]);
});

test('deleting a cuisine type in use is refused with 409', function (): void {
    actingAsAdmin();
    $cuisineType = CuisineType::factory()->create();
    $cuisineType->restaurants()->attach(Restaurant::factory()->create());

    $this->deleteJson("/api/admin/cuisine-types/{$cuisineType->id}")
        ->assertStatus(409)
        ->assertJsonPath('code', 'CUISINE_TYPE_IN_USE');

    $this->assertDatabaseHas('cuisine_types', ['id' => $cuisineType->id]);
});

test('non-admins cannot manage cuisine types', function (): void {
    $cuisineType = CuisineType::factory()->create();

    $this->postJson('/api/admin/cuisine-types', ['name' => 'X'])->assertUnauthorized();

    actingAsClient();
    $this->getJson('/api/admin/cuisine-types')->assertForbidden();
    $this->postJson('/api/admin/cuisine-types', ['name' => 'X'])->assertForbidden();
    $this->patchJson("/api/admin/cuisine-types/{$cuisineType->id}", ['name' => 'Y'])->assertForbidden();
    $this->deleteJson("/api/admin/cuisine-types/{$cuisineType->id}")->assertForbidden();
});
