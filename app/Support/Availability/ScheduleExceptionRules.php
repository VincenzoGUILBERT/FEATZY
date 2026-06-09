<?php

namespace App\Support\Availability;

use App\Enums\ScheduleExceptionType;
use Illuminate\Validation\Validator;

/**
 * Règles métier d'une dérogation datée, partagées entre création et mise à jour :
 *  - horaires spéciaux : ordre des bornes (hors franchissement de minuit) ;
 *  - capacité réduite : au moins un plafond (capacité ou pacing) doit être fourni.
 */
class ScheduleExceptionRules
{
    public static function validate(
        Validator $validator,
        ?string $type,
        bool $crossesMidnight,
        ?string $opensAt,
        ?string $lastSeatingAt,
        ?string $closesAt,
        ?int $capacityOverride,
        ?int $pacingOverride,
    ): void {
        if ($type === ScheduleExceptionType::SpecialHours->value && ! $crossesMidnight) {
            if ($opensAt !== null && $lastSeatingAt !== null && strtotime($lastSeatingAt) < strtotime($opensAt)) {
                $validator->errors()->add('last_seating_at', 'La dernière arrivée doit être après l\'ouverture.');
            }
            if ($lastSeatingAt !== null && $closesAt !== null && strtotime($closesAt) < strtotime($lastSeatingAt)) {
                $validator->errors()->add('closes_at', 'La fermeture doit être après la dernière arrivée.');
            }
        }

        if ($type === ScheduleExceptionType::ReducedCapacity->value && $capacityOverride === null && $pacingOverride === null) {
            $validator->errors()->add('capacity_override', 'Indiquez au moins une capacité réduite ou un pacing réduit.');
        }
    }
}
