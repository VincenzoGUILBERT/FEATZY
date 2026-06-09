<?php

namespace Database\Factories;

use App\Enums\ScheduleExceptionType;
use App\Models\Restaurant;
use App\Models\ScheduleException;
use App\Models\Service;
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
            'service_id' => null,
            'date' => fake()->dateTimeBetween('now', '+3 months')->format('Y-m-d'),
            'type' => ScheduleExceptionType::Closed,
            'opens_at' => null,
            'last_seating_at' => null,
            'closes_at' => null,
            'crosses_midnight' => false,
            'capacity_override' => null,
            'pacing_override' => null,
            'reason' => fake()->optional()->sentence(),
        ];
    }

    public function closed(): static
    {
        return $this->state(fn (): array => ['type' => ScheduleExceptionType::Closed]);
    }

    public function specialHours(string $opensAt = '11:00:00', string $lastSeatingAt = '13:00:00', string $closesAt = '14:30:00', bool $crossesMidnight = false): static
    {
        return $this->state(fn (): array => [
            'type' => ScheduleExceptionType::SpecialHours,
            'opens_at' => $opensAt,
            'last_seating_at' => $lastSeatingAt,
            'closes_at' => $closesAt,
            'crosses_midnight' => $crossesMidnight,
        ]);
    }

    public function reducedCapacity(?int $capacity = null, ?int $pacing = null): static
    {
        return $this->state(fn (): array => [
            'type' => ScheduleExceptionType::ReducedCapacity,
            'capacity_override' => $capacity,
            'pacing_override' => $pacing,
        ]);
    }

    /**
     * Cible un service précis (la dérogation prime alors sur une dérogation restaurant).
     */
    public function forService(Service $service): static
    {
        return $this->state(fn (): array => [
            'restaurant_id' => $service->restaurant_id,
            'service_id' => $service->id,
        ]);
    }
}
