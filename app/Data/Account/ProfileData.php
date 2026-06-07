<?php

namespace App\Data\Account;

use Spatie\LaravelData\Data;

class ProfileData extends Data
{
    public function __construct(
        public string $first_name,
        public string $last_name,
        public ?string $phone = null,
    ) {}
}
