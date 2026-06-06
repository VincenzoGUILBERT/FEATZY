<?php

namespace Database\Factories;

use App\Models\CuisineType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CuisineType>
 */
class CuisineTypeFactory extends Factory
{
    protected $model = CuisineType::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'name' => Str::title($name),
            'is_active' => true,
        ];
    }

    /**
     * Indicate that the cuisine type is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
        ]);
    }
}
