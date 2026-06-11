<?php

use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Restaurant;

beforeEach(function () {
    $this->withHeader('Origin', config('app.frontend_url'));
});

it('creates a menu item under a category of the restaurant', function () {
    $owner = actingAsRestaurateur();
    $restaurant = Restaurant::factory()->for($owner, 'owner')->create();
    $category = MenuCategory::factory()->for($restaurant)->create();

    $this->postJson("/api/restaurants/{$restaurant->id}/menu-items", [
        'menu_category_id' => $category->id,
        'name' => 'Burger',
        'price' => 1290,
    ])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Burger')
        ->assertJsonPath('data.price', 1290)
        ->assertJsonPath('data.is_available', true)
        ->assertJsonPath('data.menu_category_id', $category->id);

    $this->assertDatabaseHas('menu_items', [
        'name' => 'Burger',
        'restaurant_id' => $restaurant->id,
        'menu_category_id' => $category->id,
    ]);
});

it('rejects a category from another restaurant', function () {
    $owner = actingAsRestaurateur();
    $restaurant = Restaurant::factory()->for($owner, 'owner')->create();
    $foreignCategory = MenuCategory::factory()->create();

    $this->postJson("/api/restaurants/{$restaurant->id}/menu-items", [
        'menu_category_id' => $foreignCategory->id,
        'name' => 'X',
        'price' => 100,
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('menu_category_id');
});

it('requires category, name and price', function () {
    $owner = actingAsRestaurateur();
    $restaurant = Restaurant::factory()->for($owner, 'owner')->create();

    $this->postJson("/api/restaurants/{$restaurant->id}/menu-items", [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['menu_category_id', 'name', 'price']);
});

it('rejects negative price and stock', function () {
    $owner = actingAsRestaurateur();
    $restaurant = Restaurant::factory()->for($owner, 'owner')->create();
    $category = MenuCategory::factory()->for($restaurant)->create();

    $this->postJson("/api/restaurants/{$restaurant->id}/menu-items", [
        'menu_category_id' => $category->id,
        'name' => 'X',
        'price' => -5,
        'stock_quantity' => -1,
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['price', 'stock_quantity']);
});

it('updates a menu item', function () {
    $owner = actingAsRestaurateur();
    $restaurant = Restaurant::factory()->for($owner, 'owner')->create();
    $category = MenuCategory::factory()->for($restaurant)->create();
    $item = MenuItem::factory()->for($category, 'category')->create(['name' => 'Old']);

    $this->patchJson("/api/menu-items/{$item->id}", ['name' => 'New', 'is_available' => false])
        ->assertOk()
        ->assertJsonPath('data.name', 'New')
        ->assertJsonPath('data.is_available', false);
});

it('derives is_sold_out from the tracked stock', function () {
    $owner = actingAsRestaurateur();
    $restaurant = Restaurant::factory()->for($owner, 'owner')->create();
    $category = MenuCategory::factory()->for($restaurant)->create();
    $item = MenuItem::factory()->for($category, 'category')->create(['stock_quantity' => null]);

    $this->getJson("/api/menu-items/{$item->id}")
        ->assertOk()
        ->assertJsonPath('data.is_sold_out', false);

    $this->patchJson("/api/menu-items/{$item->id}", ['stock_quantity' => 0])
        ->assertOk()
        ->assertJsonPath('data.stock_quantity', 0)
        ->assertJsonPath('data.is_sold_out', true);
});

it('shows a menu item with its relations', function () {
    $owner = actingAsRestaurateur();
    $restaurant = Restaurant::factory()->for($owner, 'owner')->create();
    $category = MenuCategory::factory()->for($restaurant)->create();
    $item = MenuItem::factory()->for($category, 'category')->create();

    $this->getJson("/api/menu-items/{$item->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $item->id)
        ->assertJsonStructure(['data' => ['option_groups', 'allergens', 'photos']]);
});

it('soft-deletes a menu item', function () {
    $owner = actingAsRestaurateur();
    $restaurant = Restaurant::factory()->for($owner, 'owner')->create();
    $category = MenuCategory::factory()->for($restaurant)->create();
    $item = MenuItem::factory()->for($category, 'category')->create();

    $this->deleteJson("/api/menu-items/{$item->id}")->assertNoContent();

    $this->assertSoftDeleted($item);
});

it('forbids managing a menu item of another owner', function () {
    actingAsRestaurateur();
    $foreign = MenuItem::factory()->create();

    $this->patchJson("/api/menu-items/{$foreign->id}", ['name' => 'Hack'])->assertForbidden();
});
