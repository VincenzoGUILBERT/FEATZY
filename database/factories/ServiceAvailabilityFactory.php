<?php

namespace Database\Factories;

use App\Enums\ServiceType;
use App\Models\Restaurant;
use App\Models\ServiceAvailability;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServiceAvailability>
 */
class ServiceAvailabilityFactory extends Factory
{
    protected $model = ServiceAvailability::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $capacity = fake()->numberBetween(20, 120);

        return [
            'restaurant_id' => Restaurant::factory(),
            'date' => fake()->dateTimeBetween('now', '+3 months')->format('Y-m-d'),
            'service_type' => fake()->randomElement(ServiceType::cases()),
            'capacity' => $capacity,
            'booked_seats' => fake()->numberBetween(0, $capacity),
            'max_party_size' => fake()->numberBetween(2, min(12, $capacity)),
        ];
    }
}
