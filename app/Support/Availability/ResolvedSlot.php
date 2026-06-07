<?php

namespace App\Support\Availability;

/**
 * The effective bookable configuration resolved for a single (date, service)
 * slot, ready to be materialised into a service_availabilities row.
 */
final readonly class ResolvedSlot
{
    public function __construct(
        public int $capacity,
        public ?int $max_party_size,
    ) {}
}
