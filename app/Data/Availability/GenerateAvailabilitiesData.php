<?php

namespace App\Data\Availability;

use Spatie\LaravelData\Data;

class GenerateAvailabilitiesData extends Data
{
    public function __construct(
        public ?string $from = null,
        public ?string $to = null,
    ) {}
}
