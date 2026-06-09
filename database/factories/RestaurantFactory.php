<?php

namespace Database\Factories;

use App\Enums\PriceLevel;
use App\Enums\RestaurantStatus;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Restaurant>
 */
class RestaurantFactory extends Factory
{
    protected $model = Restaurant::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->company();

        return [
            'owner_id' => User::factory(),
            'name' => $name,
            'description' => fake()->paragraph(),
            'email' => fake()->companyEmail(),
            'phone' => fake()->numerify('+336########'),
            'street' => fake()->streetAddress(),
            'postal_code' => fake()->numerify('750##'),
            'city' => fake()->city(),
            'latitude' => fake()->randomFloat(7, 48.815, 48.902),
            'longitude' => fake()->randomFloat(7, 2.224, 2.469),
            'price_level' => fake()->randomElement(PriceLevel::cases()),
            'accepts_preorders' => fake()->boolean(),
            'accepts_online_payment' => fake()->boolean(),
            'cancellation_deadline_hours' => fake()->randomElement([12, 24, 48]),
            'booking_horizon_days' => fake()->randomElement([30, 60, 90]),
            'default_seating_duration_minutes' => 90,
            'slot_interval_minutes' => 15,
            'min_lead_time_minutes' => 0,
            'min_party_size' => 1,
            'max_party_size' => 20,
            'status' => RestaurantStatus::Draft,
            'average_rating' => null,
            'reviews_count' => 0,
        ];
    }

    /**
     * Indicate that the restaurant is published.
     */
    public function published(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => RestaurantStatus::Published,
        ]);
    }
}
