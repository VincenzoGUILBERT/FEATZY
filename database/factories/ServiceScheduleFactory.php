<?php

namespace Database\Factories;

use App\Enums\DayOfWeek;
use App\Models\Service;
use App\Models\ServiceSchedule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServiceSchedule>
 */
class ServiceScheduleFactory extends Factory
{
    protected $model = ServiceSchedule::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'service_id' => Service::factory(),
            'day_of_week' => fake()->randomElement(DayOfWeek::cases()),
            'opens_at' => '12:00:00',
            'last_seating_at' => '13:30:00',
            'closes_at' => '15:00:00',
            'crosses_midnight' => false,
        ];
    }

    public function onDay(DayOfWeek|int $day): static
    {
        return $this->state(fn (): array => [
            'day_of_week' => $day instanceof DayOfWeek ? $day : DayOfWeek::from($day),
        ]);
    }

    public function window(string $opensAt, string $lastSeatingAt, string $closesAt, bool $crossesMidnight = false): static
    {
        return $this->state(fn (): array => [
            'opens_at' => $opensAt,
            'last_seating_at' => $lastSeatingAt,
            'closes_at' => $closesAt,
            'crosses_midnight' => $crossesMidnight,
        ]);
    }
}
