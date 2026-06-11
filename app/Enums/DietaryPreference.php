<?php

namespace App\Enums;

enum DietaryPreference: string
{
    case Vegetarian = 'vegetarian';
    case Vegan = 'vegan';
    case GlutenFree = 'gluten_free';
    case DairyFree = 'dairy_free';
    case NutFree = 'nut_free';
    case Kosher = 'kosher';
    case Halal = 'halal';
    case Keto = 'keto';
    case Pescatarian = 'pescatarian';
    case NoRedMeat = 'no_red_meat';
    case NoSeafood = 'no_seafood';
    case NoWhiteMeat = 'no_white_meat';

    public function label(): string
    {
        return match ($this) {
            self::Vegetarian => 'Végétarien',
            self::Vegan => 'Végétalien',
            self::GlutenFree => 'Sans gluten',
            self::DairyFree => 'Sans produits laitiers',
            self::NutFree => 'Sans noix',
            self::Kosher => 'Casher',
            self::Halal => 'Halal',
            self::Keto => 'Cétogène',
            self::Pescatarian => 'Pescatarien',
            self::NoRedMeat => 'Sans viande rouge',
            self::NoSeafood => 'Sans fruits de mer',
            self::NoWhiteMeat => 'Sans viande blanche',
        };
    }
}
