<?php

namespace Database\Seeders;

use App\Enums\DayOfWeek;
use App\Enums\ServiceType;
use App\Models\Restaurant;
use App\Models\Service;
use App\Models\ServiceSchedule;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ServiceScheduleSeeder extends Seeder
{
    /**
     * Schémas d'ouverture hebdomadaires. Chaque clé pointe vers la liste des jours
     * ouverts (0 = Dimanche .. 6 = Samedi, valeurs de l'enum DayOfWeek).
     *
     * @var array<string, list<int>>
     */
    private array $openingPatterns = [
        // Mardi -> Dimanche, fermé le lundi (le pattern français le plus courant).
        'closed_monday' => [2, 3, 4, 5, 6, 0],
        // Ouvert sept jours sur sept (brasseries / lieux touristiques).
        'seven_days' => [0, 1, 2, 3, 4, 5, 6],
        // Mercredi -> Dimanche, fermé lundi + mardi (tables gastronomiques).
        'closed_monday_tuesday' => [3, 4, 5, 6, 0],
        // Mardi -> Samedi, fermé dimanche + lundi (quartier d'affaires).
        'closed_sunday_monday' => [2, 3, 4, 5, 6],
    ];

    /**
     * Définition des deux services posés sur chaque restaurant. La fenêtre horaire
     * est partagée par tous les jours ouverts ; opens_at est aligné sur 15 min.
     *
     * @var list<array{type: ServiceType, position: int, opens_at: string, last_seating_at: string, closes_at: string}>
     */
    private array $serviceBlueprints = [
        [
            'type' => ServiceType::Lunch,
            'position' => 0,
            'opens_at' => '12:00:00',
            'last_seating_at' => '13:30:00',
            'closes_at' => '15:00:00',
        ],
        [
            'type' => ServiceType::Dinner,
            'position' => 1,
            'opens_at' => '19:00:00',
            'last_seating_at' => '21:30:00',
            'closes_at' => '23:00:00',
        ],
    ];

    /**
     * Crée pour chaque restaurant deux services (Déjeuner, Dîner) et leurs horaires
     * hebdomadaires récurrents sur chaque jour ouvert.
     */
    public function run(): void
    {
        $patternKeys = array_keys($this->openingPatterns);
        $patternCount = count($patternKeys);

        Restaurant::query()
            ->orderBy('id')
            ->get()
            ->each(function (Restaurant $restaurant, int $index) use ($patternKeys, $patternCount): void {
                // Répartit les patterns de façon déterministe pour un jeu de données
                // varié mais reproductible.
                $patternKey = $patternKeys[$index % $patternCount];
                $openDays = $this->openingPatterns[$patternKey];

                // La capacité dépend du lieu : valeur stable par restaurant entre 40 et 90
                // couverts simultanés, avec un pacing cohérent (6 à 10 couverts/créneau).
                $simultaneousCovers = 40 + (($restaurant->id * 7) % 51);
                $coversPerSlot = 6 + ($restaurant->id % 5);

                $this->seedRestaurant($restaurant, $openDays, $simultaneousCovers, $coversPerSlot);
            });
    }

    /**
     * Pose les deux services du restaurant et leurs horaires sur chaque jour ouvert.
     *
     * @param  list<int>  $openDays
     */
    private function seedRestaurant(
        Restaurant $restaurant,
        array $openDays,
        int $simultaneousCovers,
        int $coversPerSlot,
    ): void {
        DB::transaction(function () use ($restaurant, $openDays, $simultaneousCovers, $coversPerSlot): void {
            foreach ($this->serviceBlueprints as $blueprint) {
                // Le dîner sert généralement un peu plus de couverts que le déjeuner.
                $serviceSimultaneous = $blueprint['type'] === ServiceType::Dinner
                    ? min($simultaneousCovers + 5, 90)
                    : $simultaneousCovers;

                $service = Service::query()->firstOrCreate(
                    [
                        'restaurant_id' => $restaurant->id,
                        'type' => $blueprint['type']->value,
                    ],
                    [
                        'name' => $blueprint['type']->label(),
                        'capacity_pool_key' => 'default',
                        'max_simultaneous_covers' => $serviceSimultaneous,
                        'max_covers_per_slot' => $coversPerSlot,
                        'position' => $blueprint['position'],
                        'is_active' => true,
                    ],
                );

                $this->seedServiceSchedules($service, $openDays, $blueprint);
            }
        });
    }

    /**
     * Crée un horaire récurrent par jour ouvert pour le service donné.
     *
     * @param  list<int>  $openDays
     * @param  array{type: ServiceType, position: int, opens_at: string, last_seating_at: string, closes_at: string}  $blueprint
     */
    private function seedServiceSchedules(Service $service, array $openDays, array $blueprint): void
    {
        foreach ($openDays as $dayOfWeek) {
            ServiceSchedule::query()->firstOrCreate(
                [
                    'service_id' => $service->id,
                    'day_of_week' => DayOfWeek::from($dayOfWeek),
                    'opens_at' => $blueprint['opens_at'],
                ],
                [
                    'last_seating_at' => $blueprint['last_seating_at'],
                    'closes_at' => $blueprint['closes_at'],
                    'crosses_midnight' => false,
                ],
            );
        }
    }
}
