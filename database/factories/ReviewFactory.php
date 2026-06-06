<?php

namespace Database\Factories;

use App\Enums\ReviewStatus;
use App\Models\Reservation;
use App\Models\Restaurant;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Review>
 */
class ReviewFactory extends Factory
{
    protected $model = Review::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'restaurant_id' => Restaurant::factory(),
            'user_id' => User::factory(),
            'reservation_id' => null,
            'rating' => fake()->numberBetween(1, 5),
            'comment' => fake()->optional()->paragraph(),
            'status' => fake()->randomElement(ReviewStatus::cases()),
        ];
    }

    /**
     * Indicate that the review is verified (linked to a reservation).
     */
    public function verified(): static
    {
        return $this->state(fn (array $attributes): array => [
            'reservation_id' => Reservation::factory(),
        ]);
    }

    /**
     * Indicate that the review is published.
     */
    public function published(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => ReviewStatus::Published,
        ]);
    }

    /**
     * Indicate that the review is pending moderation.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => ReviewStatus::Pending,
        ]);
    }
}
