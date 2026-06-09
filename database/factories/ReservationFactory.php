<?php

namespace Database\Factories;

use App\Enums\ReservationStatus;
use App\Models\Reservation;
use App\Models\Restaurant;
use App\Models\Service;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Reservation>
 */
class ReservationFactory extends Factory
{
    protected $model = Reservation::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $duration = 90;
        $reservedAt = CarbonImmutable::parse(fake()->dateTimeBetween('now', '+30 days')->format('Y-m-d').' 12:00:00');

        return [
            'public_uuid' => (string) Str::uuid(),
            'restaurant_id' => Restaurant::factory(),
            // Le service hérite du restaurant déjà résolu pour rester cohérent.
            'service_id' => fn (array $attributes): Factory => Service::factory()->state(['restaurant_id' => $attributes['restaurant_id']]),
            'organizer_id' => User::factory(),
            'party_size' => fake()->numberBetween(1, 8),
            'reserved_at' => $reservedAt,
            'slot_at' => $reservedAt,
            'ends_at' => $reservedAt->addMinutes($duration),
            'seating_duration_minutes' => $duration,
            'capacity_pool_key' => 'default',
            'status' => ReservationStatus::Confirmed,
            'is_preorder' => false,
            'special_requests' => fake()->optional()->sentence(),
            'seated_at' => null,
            'completed_at' => null,
            'cancelled_at' => null,
            'cancelled_by_id' => null,
            'cancellation_reason' => null,
        ];
    }

    /**
     * Cale la réservation sur un service et un créneau précis (couverts cohérents).
     */
    public function forSlot(Service $service, CarbonImmutable $reservedAt, int $partySize): static
    {
        $duration = $service->effectiveSeatingDuration();

        return $this->state(fn (): array => [
            'restaurant_id' => $service->restaurant_id,
            'service_id' => $service->id,
            'capacity_pool_key' => $service->capacity_pool_key,
            'party_size' => $partySize,
            'reserved_at' => $reservedAt,
            'slot_at' => $reservedAt,
            'ends_at' => $reservedAt->addMinutes($duration),
            'seating_duration_minutes' => $duration,
            'status' => ReservationStatus::Confirmed,
        ]);
    }

    public function status(ReservationStatus $status): static
    {
        return $this->state(fn (): array => ['status' => $status]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (): array => [
            'status' => ReservationStatus::Cancelled,
            'cancelled_at' => now(),
        ]);
    }
}
