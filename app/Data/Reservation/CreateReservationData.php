<?php

namespace App\Data\Reservation;

use Spatie\LaravelData\Data;

class CreateReservationData extends Data
{
    public function __construct(
        public int $service_availability_id,
        public int $party_size,
        public bool $is_preorder = false,
        public ?string $special_requests = null,
        public ?string $expected_arrival_time = null,
    ) {}
}
