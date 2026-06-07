<?php

use App\Models\Allergen;
use App\Models\Restaurant;

test('an admin can list allergens with their usage count', function (): void {
    actingAsAdmin();
    Allergen::factory()->count(2)->create();

    $this->getJson('/api/admin/allergens')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonStructure(['data' => [['id', 'name', 'icon', 'position', 'menu_items_count']]]);
});

test('an admin can create an allergen', function (): void {
    actingAsAdmin();

    $this->postJson('/api/admin/allergens', ['name' => 'Gluten', 'position' => 0])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Gluten');

    $this->assertDatabaseHas('allergens', ['name' => 'Gluten']);
});

test('an allergen name must be unique on create', function (): void {
    actingAsAdmin();
    Allergen::factory()->create(['name' => 'Gluten']);

    $this->postJson('/api/admin/allergens', ['name' => 'Gluten'])
        ->assertUnprocessable()->assertJsonValidationErrorFor('name');
});

test('an admin can update an allergen, ignoring its own name in the unique check', function (): void {
    actingAsAdmin();
    $allergen = Allergen::factory()->create(['name' => 'Gluten', 'position' => 0]);

    $this->patchJson("/api/admin/allergens/{$allergen->id}", ['name' => 'Gluten', 'position' => 5])
        ->assertOk()
        ->assertJsonPath('data.position', 5);
});

test('an admin can delete an unused allergen', function (): void {
    actingAsAdmin();
    $allergen = Allergen::factory()->create();

    $this->deleteJson("/api/admin/allergens/{$allergen->id}")->assertNoContent();

    $this->assertDatabaseMissing('allergens', ['id' => $allergen->id]);
});

test('deleting an allergen in use is refused with 409', function (): void {
    actingAsAdmin();
    $allergen = Allergen::factory()->create();
    $menuItem = menuItemFor(Restaurant::factory()->create());
    $allergen->menuItems()->attach($menuItem);

    $this->deleteJson("/api/admin/allergens/{$allergen->id}")
        ->assertStatus(409)
        ->assertJsonPath('code', 'ALLERGEN_IN_USE');

    $this->assertDatabaseHas('allergens', ['id' => $allergen->id]);
});

test('non-admins cannot manage allergens', function (): void {
    $allergen = Allergen::factory()->create();

    $this->postJson('/api/admin/allergens', ['name' => 'X'])->assertUnauthorized();

    actingAsClient();
    $this->getJson('/api/admin/allergens')->assertForbidden();
    $this->postJson('/api/admin/allergens', ['name' => 'X'])->assertForbidden();
    $this->patchJson("/api/admin/allergens/{$allergen->id}", ['name' => 'Y'])->assertForbidden();
    $this->deleteJson("/api/admin/allergens/{$allergen->id}")->assertForbidden();
});
