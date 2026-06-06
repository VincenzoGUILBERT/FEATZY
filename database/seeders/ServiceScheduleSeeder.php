<?php

namespace Database\Seeders;

use App\Enums\ServiceType;
use App\Models\Restaurant;
use App\Models\ServiceSchedule;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ServiceScheduleSeeder extends Seeder
{
    /**
     * Weekly opening patterns. Each key maps to the list of open
     * days of week (0 = Sunday .. 6 = Saturday) using the DayOfWeek enum.
     *
     * @var array<string, list<int>>
     */
    private array $openingPatterns = [
        // Tuesday -> Sunday, closed on Monday (the most common French pattern).
        'closed_monday' => [2, 3, 4, 5, 6, 0],
        // Open seven days a week (brasseries / touristic spots).
        'seven_days' => [0, 1, 2, 3, 4, 5, 6],
        // Wednesday -> Sunday, closed Monday + Tuesday (gastronomic places).
        'closed_monday_tuesday' => [3, 4, 5, 6, 0],
        // Tuesday -> Saturday, closed Sunday + Monday (business district).
        'closed_sunday_monday' => [2, 3, 4, 5, 6],
    ];

    /**
     * Lunch service window shared by every restaurant.
     *
     * @var array{start: string, end: string}
     */
    private array $lunchWindow = ['start' => '12:00:00', 'end' => '14:30:00'];

    /**
     * Dinner service window shared by every restaurant.
     *
     * @var array{start: string, end: string}
     */
    private array $dinnerWindow = ['start' => '19:00:00', 'end' => '22:30:00'];

    /**
     * Seed the recurring weekly service schedules for every restaurant.
     */
    public function run(): void
    {
        $patternKeys = array_keys($this->openingPatterns);
        $patternCount = count($patternKeys);

        Restaurant::query()
            ->orderBy('id')
            ->get()
            ->each(function (Restaurant $restaurant, int $index) use ($patternKeys, $patternCount): void {
                // Spread the patterns deterministically across restaurants so the
                // seeded dataset stays varied yet reproducible.
                $patternKey = $patternKeys[$index % $patternCount];
                $openDays = $this->openingPatterns[$patternKey];

                // Capacity scales with the venue: derive a stable size per restaurant
                // between 40 and 90 covers, with a coherent max party size (8 to 10).
                $capacity = 40 + (($restaurant->id * 7) % 51);
                $maxPartySize = 8 + ($restaurant->id % 3);

                $this->createWeeklySchedule($restaurant, $openDays, $capacity, $maxPartySize);
            });
    }

    /**
     * Create the lunch and dinner schedules for each open day of a restaurant.
     *
     * @param  list<int>  $openDays
     */
    private function createWeeklySchedule(
        Restaurant $restaurant,
        array $openDays,
        int $capacity,
        int $maxPartySize,
    ): void {
        DB::transaction(function () use ($restaurant, $openDays, $capacity, $maxPartySize): void {
            foreach ($openDays as $dayOfWeek) {
                foreach ([ServiceType::Lunch, ServiceType::Dinner] as $serviceType) {
                    $window = $serviceType === ServiceType::Lunch
                        ? $this->lunchWindow
                        : $this->dinnerWindow;

                    // Dinner usually serves slightly more covers than lunch.
                    $serviceCapacity = $serviceType === ServiceType::Dinner
                        ? min($capacity + 5, 90)
                        : $capacity;

                    ServiceSchedule::query()->firstOrCreate(
                        [
                            'restaurant_id' => $restaurant->id,
                            'day_of_week' => $dayOfWeek,
                            'service_type' => $serviceType->value,
                        ],
                        [
                            'start_time' => $window['start'],
                            'end_time' => $window['end'],
                            'capacity' => $serviceCapacity,
                            'max_party_size' => $maxPartySize,
                            'is_active' => true,
                        ],
                    );
                }
            }
        });
    }
}
