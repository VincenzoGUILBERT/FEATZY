<?php

use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\MenuItemOption;
use App\Models\MenuItemOptionGroup;
use App\Models\Restaurant;

beforeEach(function () {
    $this->withHeader('Origin', config('app.frontend_url'));
});

function ownedOptionGroupForOptions(): MenuItemOptionGroup
{
    $owner = actingAsRestaurateur();
    $restaurant = Restaurant::factory()->for($owner, 'owner')->create();
    $category = MenuCategory::factory()->for($restaurant)->create();
    $item = MenuItem::factory()->for($category, 'category')->create();

    return MenuItemOptionGroup::factory()->for($item, 'menuItem')->create();
}

it('creates an option under a group', function () {
    $group = ownedOptionGroupForOptions();

    $this->postJson("/api/menu-item-option-groups/{$group->id}/options", [
        'name' => 'Bien cuit',
        'price_delta' => 0,
    ])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Bien cuit')
        ->assertJsonPath('data.option_group_id', $group->id)
        ->assertJsonPath('data.is_available', true);
});

it('allows a negative price delta', function () {
    $group = ownedOptionGroupForOptions();

    $this->postJson("/api/menu-item-option-groups/{$group->id}/options", [
        'name' => 'Sans sauce',
        'price_delta' => -150,
    ])
        ->assertCreated()
        ->assertJsonPath('data.price_delta', -150);
});

it('requires name and price_delta', function () {
    $group = ownedOptionGroupForOptions();

    $this->postJson("/api/menu-item-option-groups/{$group->id}/options", [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['name', 'price_delta']);
});

it('rejects negative stock', function () {
    $group = ownedOptionGroupForOptions();

    $this->postJson("/api/menu-item-option-groups/{$group->id}/options", [
        'name' => 'X',
        'price_delta' => 0,
        'stock_quantity' => -1,
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('stock_quantity');
});

it('updates an option', function () {
    $group = ownedOptionGroupForOptions();
    $option = MenuItemOption::factory()->for($group, 'group')->create(['name' => 'Old']);

    $this->patchJson("/api/menu-item-options/{$option->id}", ['name' => 'New', 'is_available' => false])
        ->assertOk()
        ->assertJsonPath('data.name', 'New')
        ->assertJsonPath('data.is_available', false);
});

it('derives is_sold_out from the tracked stock', function () {
    $group = ownedOptionGroupForOptions();
    $option = MenuItemOption::factory()->for($group, 'group')->create(['stock_quantity' => null]);

    $this->patchJson("/api/menu-item-options/{$option->id}", ['stock_quantity' => 0])
        ->assertOk()
        ->assertJsonPath('data.stock_quantity', 0)
        ->assertJsonPath('data.is_sold_out', true);
});

it('deletes an option', function () {
    $group = ownedOptionGroupForOptions();
    $option = MenuItemOption::factory()->for($group, 'group')->create();

    $this->deleteJson("/api/menu-item-options/{$option->id}")->assertNoContent();

    $this->assertDatabaseMissing('menu_item_options', ['id' => $option->id]);
});

it('forbids managing an option of another owner', function () {
    actingAsRestaurateur();
    $foreign = MenuItemOption::factory()->create();

    $this->patchJson("/api/menu-item-options/{$foreign->id}", ['name' => 'Hack'])
        ->assertForbidden();
});
