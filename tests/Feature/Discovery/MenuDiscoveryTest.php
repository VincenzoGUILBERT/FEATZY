<?php

use App\Models\Allergen;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\MenuItemOption;
use App\Models\MenuItemOptionGroup;
use App\Models\Restaurant;

beforeEach(function () {
    $this->withHeader('Origin', config('app.frontend_url'));
});

it('returns the active menu tree of a published restaurant', function () {
    $restaurant = Restaurant::factory()->published()->create();
    $category = MenuCategory::factory()->for($restaurant)->create(['is_active' => true, 'position' => 1]);
    $item = MenuItem::factory()->for($restaurant)->for($category, 'category')->create([
        'is_available' => true, 'position' => 1, 'name' => 'Burger',
    ]);
    $group = MenuItemOptionGroup::factory()->for($item, 'menuItem')->create(['name' => 'Cuisson', 'position' => 1]);
    MenuItemOption::factory()->for($group, 'group')->create(['name' => 'Saignant', 'position' => 1]);
    $allergen = Allergen::factory()->create(['name' => 'Gluten']);
    $item->allergens()->attach($allergen);

    $this->getJson("/api/discovery/restaurants/{$restaurant->id}/menu")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', $category->name)
        ->assertJsonPath('data.0.menu_items.0.name', 'Burger')
        ->assertJsonPath('data.0.menu_items.0.option_groups.0.name', 'Cuisson')
        ->assertJsonPath('data.0.menu_items.0.option_groups.0.options.0.name', 'Saignant')
        ->assertJsonPath('data.0.menu_items.0.allergens.0.name', 'Gluten');
});

it('hides inactive categories and unavailable items', function () {
    $restaurant = Restaurant::factory()->published()->create();
    MenuCategory::factory()->for($restaurant)->create(['is_active' => false]);
    $active = MenuCategory::factory()->for($restaurant)->create(['is_active' => true]);
    MenuItem::factory()->for($restaurant)->for($active, 'category')->create(['is_available' => false]);

    $this->getJson("/api/discovery/restaurants/{$restaurant->id}/menu")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $active->id)
        ->assertJsonCount(0, 'data.0.menu_items');
});

it('orders the menu tree by position', function () {
    $restaurant = Restaurant::factory()->published()->create();
    MenuCategory::factory()->for($restaurant)->create(['is_active' => true, 'position' => 2, 'name' => 'Second']);
    $first = MenuCategory::factory()->for($restaurant)->create(['is_active' => true, 'position' => 1, 'name' => 'First']);
    MenuItem::factory()->for($restaurant)->for($first, 'category')->create(['is_available' => true, 'position' => 2, 'name' => 'Item B']);
    MenuItem::factory()->for($restaurant)->for($first, 'category')->create(['is_available' => true, 'position' => 1, 'name' => 'Item A']);

    $this->getJson("/api/discovery/restaurants/{$restaurant->id}/menu")
        ->assertOk()
        ->assertJsonPath('data.0.name', 'First')
        ->assertJsonPath('data.1.name', 'Second')
        ->assertJsonPath('data.0.menu_items.0.name', 'Item A')
        ->assertJsonPath('data.0.menu_items.1.name', 'Item B');
});

it('returns 404 for the menu of a draft restaurant', function () {
    $restaurant = Restaurant::factory()->create();

    $this->getJson("/api/discovery/restaurants/{$restaurant->id}/menu")->assertNotFound();
});
