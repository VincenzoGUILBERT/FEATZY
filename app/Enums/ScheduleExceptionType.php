<?php

namespace App\Enums;

enum ScheduleExceptionType: string
{
    case Closed = 'closed';
    case SpecialHours = 'special_hours';
    case ReducedCapacity = 'reduced_capacity';

    public function label(): string
    {
        return match ($this) {
            self::Closed => 'Fermeture',
            self::SpecialHours => 'Horaires spéciaux',
            self::ReducedCapacity => 'Capacité réduite',
        };
    }
}
