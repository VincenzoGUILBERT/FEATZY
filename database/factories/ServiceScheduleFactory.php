<?php

namespace Database\Factories;

use App\Enums\DayOfWeek;
use App\Enums\ServiceType;
use App\Models\Restaurant;
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
        $serviceType = fake()->randomElement(ServiceType::cases());

        [$startTime, $endTime] = $serviceType === ServiceType::Lunch
            ? ['12:00:00', '14:30:00']
            : ['19:00:00', '22:30:00'];

        $capacity = fake()->numberBetween(20, 120);

        return [
            'restaurant_id' => Restaurant::factory(),
            'day_of_week' => fake()->randomElement(DayOfWeek::cases()),
            'service_type' => $serviceType,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'capacity' => $capacity,
            'max_party_size' => fake()->numberBetween(2, min(12, $capacity)),
            'is_active' => true,
        ];
    }
}
