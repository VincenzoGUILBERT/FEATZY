<?php

namespace Database\Factories;

use App\Models\MenuCategory;
use App\Models\Restaurant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MenuCategory>
 */
class MenuCategoryFactory extends Factory
{
    protected $model = MenuCategory::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'restaurant_id' => Restaurant::factory(),
            'name' => fake()->randomElement(['Entrées', 'Plats', 'Desserts', 'Boissons', 'Menus']),
            'description' => fake()->optional()->sentence(),
            'position' => fake()->numberBetween(0, 10),
            'is_active' => true,
        ];
    }
}
