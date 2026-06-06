<?php

namespace App\Data\Auth;

use Spatie\LaravelData\Data;

class RegisterUserData extends Data
{
    public function __construct(
        public string $first_name,
        public string $last_name,
        public string $email,
        public string $password,
        public ?string $phone = null,
    ) {}
}
