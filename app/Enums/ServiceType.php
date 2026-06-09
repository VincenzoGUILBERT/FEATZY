<?php

namespace App\Enums;

enum ServiceType: string
{
    case Lunch = 'lunch';
    case Dinner = 'dinner';
    case Brunch = 'brunch';
    case Continuous = 'continuous';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Lunch => 'Déjeuner',
            self::Dinner => 'Dîner',
            self::Brunch => 'Brunch',
            self::Continuous => 'Service continu',
            self::Other => 'Autre',
        };
    }
}
