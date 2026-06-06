<?php

namespace Database\Factories;

use App\Enums\ReservationStatus;
use App\Enums\ServiceType;
use App\Models\Reservation;
use App\Models\Restaurant;
use App\Models\ServiceAvailability;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Reservation>
 */
class ReservationFactory extends Factory
{
    protected $model = Reservation::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $restaurant = Restaurant::factory();
        $serviceAvailability = ServiceAvailability::factory()->for($restaurant);
        $serviceType = fake()->randomElement(ServiceType::cases());

        return [
            'public_uuid' => (string) Str::uuid(),
            'restaurant_id' => $restaurant,
            'service_availability_id' => $serviceAvailability,
            'organizer_id' => User::factory(),
            'reservation_date' => fake()->dateTimeBetween('now', '+30 days')->format('Y-m-d'),
            'service_type' => $serviceType,
            'party_size' => fake()->numberBetween(1, 8),
            'status' => ReservationStatus::Confirmed,
            'is_preorder' => false,
            'special_requests' => fake()->optional()->sentence(),
            'expected_arrival_time' => fake()->optional()->time('H:i:s'),
            'seated_at' => null,
            'completed_at' => null,
            'cancelled_at' => null,
            'cancelled_by_id' => null,
            'cancellation_reason' => null,
        ];
    }
}
