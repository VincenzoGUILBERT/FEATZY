<?php

namespace Database\Factories;

use App\Models\MenuItem;
use App\Models\MenuItemOptionGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MenuItemOptionGroup>
 */
class MenuItemOptionGroupFactory extends Factory
{
    protected $model = MenuItemOptionGroup::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $minSelect = fake()->numberBetween(0, 2);

        return [
            'menu_item_id' => MenuItem::factory(),
            'name' => fake()->randomElement(['Taille', 'Cuisson', 'Suppléments', 'Accompagnement', 'Sauce']),
            'min_select' => $minSelect,
            'max_select' => fake()->optional()->numberBetween($minSelect, $minSelect + 3),
            'is_required' => $minSelect >= 1,
            'position' => fake()->numberBetween(0, 10),
        ];
    }
}
