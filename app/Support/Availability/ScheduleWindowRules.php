<?php

namespace App\Support\Availability;

use App\Models\Service;
use Illuminate\Validation\Validator;

/**
 * Règles de validation partagées d'une fenêtre d'horaire (service_schedules /
 * dérogations horaires spéciaux) : ordre des bornes hors franchissement de minuit et
 * alignement de l'ouverture sur l'intervalle de créneau effectif du service.
 */
class ScheduleWindowRules
{
    public static function validate(
        Validator $validator,
        Service $service,
        string $opensAt,
        string $lastSeatingAt,
        string $closesAt,
        bool $crossesMidnight,
    ): void {
        if (! $crossesMidnight) {
            if ($lastSeatingAt < $opensAt) {
                $validator->errors()->add('last_seating_at', 'La dernière arrivée doit être après l\'ouverture.');
            }
            if ($closesAt < $lastSeatingAt) {
                $validator->errors()->add('closes_at', 'La fermeture doit être après la dernière arrivée.');
            }
        }

        $service->loadMissing('restaurant');
        $interval = $service->effectiveSlotInterval();

        if (self::minutesOfDay($opensAt) % $interval !== 0) {
            $validator->errors()->add('opens_at', "L'heure d'ouverture doit être alignée sur des multiples de {$interval} minutes.");
        }
    }

    private static function minutesOfDay(string $time): int
    {
        [$hours, $minutes] = array_map('intval', explode(':', $time));

        return $hours * 60 + $minutes;
    }
}
