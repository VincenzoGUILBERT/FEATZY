<?php

namespace App\Events\Reservation;

use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ReservationCancelled
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Reservation $reservation,
        public User $cancelledBy,
    ) {}
}
