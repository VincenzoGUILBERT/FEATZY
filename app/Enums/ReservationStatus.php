<?php

namespace App\Enums;

enum ReservationStatus: string
{
    case Confirmed = 'confirmed';
    case Seated = 'seated';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case NoShow = 'no_show';

    /**
     * Statuts consommant de la capacité (à venir ou installés en salle). Seuls
     * ceux-ci comptent dans les calculs de couverts simultanés et de pacing.
     *
     * @return array<int, self>
     */
    public static function occupyingCapacity(): array
    {
        return [self::Confirmed, self::Seated];
    }

    /**
     * Statuts terminaux : la réservation ne consomme plus de capacité et ne peut
     * plus transitionner.
     */
    public function isTerminal(): bool
    {
        return in_array($this, [self::Completed, self::Cancelled, self::NoShow], true);
    }
}
