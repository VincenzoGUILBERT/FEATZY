<?php

namespace Database\Factories;

use App\Enums\ServiceType;
use App\Models\Restaurant;
use App\Models\ScheduleException;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ScheduleException>
 */
class ScheduleExceptionFactory extends Factory
{
    protected $model = ScheduleException::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'restaurant_id' => Restaurant::factory(),
            'date' => fake()->dateTimeBetween('now', '+3 months')->format('Y-m-d'),
            'service_type' => fake()->boolean() ? fake()->randomElement(ServiceType::cases()) : null,
            'is_closed' => false,
            'capacity' => fake()->numberBetween(0, 80),
            'max_party_size' => fake()->numberBetween(2, 12),
            'start_time' => null,
            'end_time' => null,
            'reason' => fake()->optional()->sentence(),
        ];
    }

    /**
     * Indicate that the restaurant is fully closed on this date/service.
     */
    public function closed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_closed' => true,
            'capacity' => 0,
            'max_party_size' => null,
            'start_time' => null,
            'end_time' => null,
        ]);
    }
}
