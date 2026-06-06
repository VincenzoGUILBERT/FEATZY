<?php

namespace Database\Seeders;

use App\Enums\ServiceType;
use App\Models\Restaurant;
use App\Models\ScheduleException;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ScheduleExceptionSeeder extends Seeder
{
    /**
     * 2026 French public holidays applied as full-day closures.
     * service_type is left NULL so the whole day is closed.
     *
     * @var array<string, string>
     */
    private array $publicHolidays = [
        '2026-05-01' => 'Fête du Travail',
        '2026-07-14' => 'Fête nationale',
        '2026-08-15' => 'Assomption',
        '2026-11-01' => 'Toussaint',
        '2026-11-11' => 'Armistice 1918',
        '2026-12-25' => 'Noël',
    ];

    /**
     * Seed a curated, varied set of schedule exceptions across restaurants.
     *
     * Distribution:
     *  - public-holiday full-day closures (shared by a few restaurants),
     *  - summer vacation ("congés annuels") week-long closures,
     *  - reduced-capacity overrides for high-demand special dates.
     */
    public function run(): void
    {
        $restaurants = Restaurant::query()->orderBy('id')->get();

        if ($restaurants->isEmpty()) {
            return;
        }

        $this->seedPublicHolidayClosures($restaurants);
        $this->seedSummerVacationClosures($restaurants);
        $this->seedReducedCapacityDates($restaurants);
    }

    /**
     * Close a handful of restaurants on the major 2026 public holidays.
     *
     * @param  Collection<int, Restaurant>  $restaurants
     */
    private function seedPublicHolidayClosures(Collection $restaurants): void
    {
        // Only the first few restaurants observe the bank-holiday closures so
        // the dataset shows a mix of open and closed venues on those dates.
        $observing = $restaurants->take(4);

        foreach ($observing as $restaurant) {
            foreach ($this->publicHolidays as $date => $reason) {
                $this->upsertException(
                    restaurant: $restaurant,
                    date: $date,
                    serviceType: null,
                    attributes: [
                        'is_closed' => true,
                        'reason' => "Fermeture {$reason}",
                    ],
                );
            }
        }
    }

    /**
     * Close two restaurants for their annual summer holidays.
     *
     * @param  Collection<int, Restaurant>  $restaurants
     */
    private function seedSummerVacationClosures(Collection $restaurants): void
    {
        // First venue: classic August closing week.
        $first = $restaurants->first();

        if ($first !== null) {
            foreach ($this->datesBetween('2026-08-03', '2026-08-09') as $date) {
                $this->upsertException(
                    restaurant: $first,
                    date: $date,
                    serviceType: null,
                    attributes: [
                        'is_closed' => true,
                        'reason' => 'Congés annuels',
                    ],
                );
            }
        }

        // A different venue: late-August closing week.
        $second = $restaurants->skip(2)->first();

        if ($second !== null && $second->isNot($first)) {
            foreach ($this->datesBetween('2026-08-17', '2026-08-23') as $date) {
                $this->upsertException(
                    restaurant: $second,
                    date: $date,
                    serviceType: null,
                    attributes: [
                        'is_closed' => true,
                        'reason' => 'Congés annuels',
                    ],
                );
            }
        }
    }

    /**
     * Apply reduced-capacity overrides on a few special / high-demand dates.
     *
     * @param  Collection<int, Restaurant>  $restaurants
     */
    private function seedReducedCapacityDates(Collection $restaurants): void
    {
        $reducedDates = [
            // Valentine's Day dinner: full booking, smaller covers, no walk-ins.
            [
                'date' => '2026-02-14',
                'service_type' => ServiceType::Dinner,
                'capacity' => 24,
                'max_party_size' => 4,
                'reason' => 'Saint-Valentin - menu spécial à capacité réduite',
            ],
            // New Year's Eve gala dinner with a single seating.
            [
                'date' => '2026-12-31',
                'service_type' => ServiceType::Dinner,
                'capacity' => 30,
                'max_party_size' => 6,
                'reason' => 'Réveillon du Nouvel An - service unique',
            ],
            // A private event blocks part of the lunch service.
            [
                'date' => '2026-06-20',
                'service_type' => ServiceType::Lunch,
                'capacity' => 15,
                'max_party_size' => 6,
                'reason' => 'Privatisation partielle - capacité réduite',
            ],
        ];

        // Spread each reduced-capacity override onto a distinct restaurant.
        $targets = $restaurants->skip(4)->take(count($reducedDates))->values();

        if ($targets->isEmpty()) {
            $targets = $restaurants->take(count($reducedDates))->values();
        }

        foreach ($reducedDates as $offset => $override) {
            $restaurant = $targets->get($offset % $targets->count());

            if ($restaurant === null) {
                continue;
            }

            $this->upsertException(
                restaurant: $restaurant,
                date: $override['date'],
                serviceType: $override['service_type'],
                attributes: [
                    'is_closed' => false,
                    'capacity' => $override['capacity'],
                    'max_party_size' => $override['max_party_size'],
                    'reason' => $override['reason'],
                ],
            );
        }
    }

    /**
     * Idempotently create a schedule exception.
     *
     * service_type_key is a generated column and must never be written.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function upsertException(
        Restaurant $restaurant,
        string $date,
        ?ServiceType $serviceType,
        array $attributes,
    ): void {
        DB::transaction(function () use ($restaurant, $date, $serviceType, $attributes): void {
            ScheduleException::query()->firstOrCreate(
                [
                    'restaurant_id' => $restaurant->id,
                    'date' => $date,
                    'service_type' => $serviceType?->value,
                ],
                $attributes,
            );
        });
    }

    /**
     * Build the inclusive list of ISO dates between two days.
     *
     * @return list<string>
     */
    private function datesBetween(string $start, string $end): array
    {
        $dates = [];
        $cursor = CarbonImmutable::parse($start);
        $last = CarbonImmutable::parse($end);

        while ($cursor->lessThanOrEqualTo($last)) {
            $dates[] = $cursor->toDateString();
            $cursor = $cursor->addDay();
        }

        return $dates;
    }
}
