<?php

namespace Database\Factories;

use App\Models\Allergen;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Allergen>
 */
class AllergenFactory extends Factory
{
    protected $model = Allergen::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->randomElement([
            'Gluten', 'Crustacés', 'Œufs', 'Poisson', 'Arachides',
            'Soja', 'Lait', 'Fruits à coque', 'Céleri', 'Moutarde',
            'Sésame', 'Sulfites', 'Lupin', 'Mollusques',
        ]);

        return [
            'name' => $name,
            'icon' => fake()->optional()->word(),
            'position' => fake()->numberBetween(0, 20),
        ];
    }
}
