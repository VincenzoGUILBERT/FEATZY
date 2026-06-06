<?php

namespace App\Enums;

enum RestaurantStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
}
