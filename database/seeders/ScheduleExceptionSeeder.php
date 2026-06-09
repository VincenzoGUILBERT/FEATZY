<?php

namespace Database\Seeders;

use App\Enums\ScheduleExceptionType;
use App\Models\Restaurant;
use App\Models\ScheduleException;
use App\Models\Service;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ScheduleExceptionSeeder extends Seeder
{
    /**
     * Jours fériés français 2026 appliqués comme fermetures journée entière.
     * service_id reste NULL : la dérogation ferme tout le restaurant.
     *
     * @var array<string, string>
     */
    private array $publicHolidays = [
        '2026-07-14' => 'Fête nationale',
        '2026-08-15' => 'Assomption',
        '2026-11-01' => 'Toussaint',
        '2026-11-11' => 'Armistice 1918',
        '2026-12-25' => 'Noël',
    ];

    /**
     * Sème un jeu varié et déterministe de dérogations sur quelques restaurants :
     *  - fermetures jours fériés (restaurant-wide, service_id null),
     *  - horaires spéciaux sur un service précis,
     *  - capacité réduite (capacity_override et/ou pacing_override) sur un service.
     */
    public function run(): void
    {
        $restaurants = Restaurant::query()->orderBy('id')->get();

        if ($restaurants->isEmpty()) {
            return;
        }

        $this->seedPublicHolidayClosures($restaurants);
        $this->seedSpecialHours($restaurants);
        $this->seedReducedCapacity($restaurants);
    }

    /**
     * Ferme les premiers restaurants sur les jours fériés majeurs (service_id null).
     *
     * @param  Collection<int, Restaurant>  $restaurants
     */
    private function seedPublicHolidayClosures(Collection $restaurants): void
    {
        // Seuls les premiers restaurants observent ces fermetures, pour qu'on ait un
        // mélange de venues ouvertes et fermées à ces dates.
        foreach ($restaurants->take(4) as $restaurant) {
            foreach ($this->publicHolidays as $date => $reason) {
                $this->upsertException(
                    restaurant: $restaurant,
                    service: null,
                    date: $date,
                    type: ScheduleExceptionType::Closed,
                    attributes: [
                        'reason' => "Fermeture {$reason}",
                    ],
                );
            }
        }
    }

    /**
     * Applique des horaires spéciaux ciblés sur un service précis de quelques restaurants.
     *
     * @param  Collection<int, Restaurant>  $restaurants
     */
    private function seedSpecialHours(Collection $restaurants): void
    {
        // Brunch de fête : ouverture élargie sur le service déjeuner.
        $specialDates = [
            [
                'date' => '2026-12-24',
                'opens_at' => '11:00:00',
                'last_seating_at' => '14:00:00',
                'closes_at' => '15:30:00',
                'reason' => 'Réveillon de Noël - service déjeuner prolongé',
            ],
            // Veille de jour férié : service écourté.
            [
                'date' => '2026-07-13',
                'opens_at' => '12:00:00',
                'last_seating_at' => '13:30:00',
                'closes_at' => '15:00:00',
                'reason' => 'Veille de fête nationale - service réduit',
            ],
        ];

        foreach ($restaurants->take(count($specialDates)) as $offset => $restaurant) {
            $service = $this->firstActiveService($restaurant);

            if ($service === null) {
                continue;
            }

            $override = $specialDates[$offset];

            $this->upsertException(
                restaurant: $restaurant,
                service: $service,
                date: $override['date'],
                type: ScheduleExceptionType::SpecialHours,
                attributes: [
                    'opens_at' => $override['opens_at'],
                    'last_seating_at' => $override['last_seating_at'],
                    'closes_at' => $override['closes_at'],
                    'crosses_midnight' => false,
                    'reason' => $override['reason'],
                ],
            );
        }
    }

    /**
     * Applique des dérogations de capacité réduite sur un service précis.
     *
     * @param  Collection<int, Restaurant>  $restaurants
     */
    private function seedReducedCapacity(Collection $restaurants): void
    {
        $reducedDates = [
            // Saint-Valentin : menu spécial, salle bridée et cadence ralentie.
            [
                'date' => '2026-02-14',
                'capacity_override' => 24,
                'pacing_override' => 4,
                'reason' => 'Saint-Valentin - menu spécial à capacité réduite',
            ],
            // Réveillon du Nouvel An : service unique, capacité limitée.
            [
                'date' => '2026-12-31',
                'capacity_override' => 30,
                'pacing_override' => null,
                'reason' => 'Réveillon du Nouvel An - service unique',
            ],
            // Privatisation partielle : seule la cadence d'arrivée est bridée.
            [
                'date' => '2026-06-20',
                'capacity_override' => null,
                'pacing_override' => 3,
                'reason' => 'Privatisation partielle - cadence réduite',
            ],
        ];

        // On vise des restaurants distincts de ceux des horaires spéciaux quand c'est
        // possible, sinon on retombe sur les premiers.
        $targets = $restaurants->skip(2)->take(count($reducedDates))->values();

        if ($targets->isEmpty()) {
            $targets = $restaurants->take(count($reducedDates))->values();
        }

        foreach ($reducedDates as $offset => $override) {
            $restaurant = $targets->get($offset % $targets->count());

            if ($restaurant === null) {
                continue;
            }

            $service = $this->firstActiveService($restaurant);

            if ($service === null) {
                continue;
            }

            $this->upsertException(
                restaurant: $restaurant,
                service: $service,
                date: $override['date'],
                type: ScheduleExceptionType::ReducedCapacity,
                attributes: [
                    'capacity_override' => $override['capacity_override'],
                    'pacing_override' => $override['pacing_override'],
                    'reason' => $override['reason'],
                ],
            );
        }
    }

    /**
     * Premier service actif d'un restaurant (par position), ou null.
     */
    private function firstActiveService(Restaurant $restaurant): ?Service
    {
        return Service::query()
            ->where('restaurant_id', $restaurant->id)
            ->where('is_active', true)
            ->orderBy('position')
            ->orderBy('id')
            ->first();
    }

    /**
     * Crée idempotemment une dérogation en respectant l'unicité
     * (restaurant, service, date, type).
     *
     * @param  array<string, mixed>  $attributes
     */
    private function upsertException(
        Restaurant $restaurant,
        ?Service $service,
        string $date,
        ScheduleExceptionType $type,
        array $attributes,
    ): void {
        DB::transaction(function () use ($restaurant, $service, $date, $type, $attributes): void {
            ScheduleException::query()->firstOrCreate(
                [
                    'restaurant_id' => $restaurant->id,
                    'service_id' => $service?->id,
                    'date' => $date,
                    'type' => $type->value,
                ],
                $attributes,
            );
        });
    }
}
