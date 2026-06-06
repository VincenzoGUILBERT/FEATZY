<?php

namespace Database\Factories;

use App\Models\MenuCategory;
use App\Models\MenuItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MenuItem>
 */
class MenuItemFactory extends Factory
{
    protected $model = MenuItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->words(2, true);

        return [
            'menu_category_id' => MenuCategory::factory(),
            'restaurant_id' => function (array $attributes): int {
                return MenuCategory::find($attributes['menu_category_id'])->restaurant_id;
            },
            'name' => $name,
            'description' => fake()->optional()->sentence(),
            'price' => fake()->numberBetween(500, 5000),
            'is_available' => true,
            'position' => fake()->numberBetween(0, 20),
            'stock_quantity' => fake()->optional()->numberBetween(0, 100),
            'preparation_minutes' => fake()->optional()->numberBetween(5, 45),
        ];
    }
}
