<?php

namespace Database\Factories;

use App\Models\MenuItemOption;
use App\Models\MenuItemOptionGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MenuItemOption>
 */
class MenuItemOptionFactory extends Factory
{
    protected $model = MenuItemOption::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'option_group_id' => MenuItemOptionGroup::factory(),
            'name' => fake()->word(),
            'price_delta' => fake()->numberBetween(-200, 500),
            'stock_quantity' => fake()->optional()->numberBetween(0, 100),
            'is_available' => true,
            'position' => fake()->numberBetween(0, 10),
        ];
    }
}
