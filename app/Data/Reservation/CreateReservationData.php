<?php

namespace App\Data\Reservation;

use Spatie\LaravelData\Data;

class CreateReservationData extends Data
{
    public function __construct(
        public int $service_id,
        // Date calendaire du service (Y-m-d) : porte la résolution des horaires, y compris
        // pour les créneaux d'après-minuit d'un service franchissant minuit.
        public string $date,
        // Créneau d'arrivée choisi (datetime local), tel que retourné par la disponibilité.
        public string $reserved_at,
        public int $party_size,
        public bool $is_preorder = false,
        public ?string $special_requests = null,
    ) {}
}
