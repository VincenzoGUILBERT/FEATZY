<?php

namespace App\Policies;

use App\Models\Reservation;
use App\Models\User;

class ReservationPolicy
{
    /**
     * The organizer, any participant, or the restaurant owner may view a
     * reservation. Relationship queries are used instead of loaded relations so
     * the check never triggers lazy loading.
     */
    public function view(User $user, Reservation $reservation): bool
    {
        if ($reservation->organizer_id === $user->id) {
            return true;
        }

        if ($reservation->restaurant()->where('owner_id', $user->id)->exists()) {
            return true;
        }

        return $reservation->participants()->where('user_id', $user->id)->exists();
    }

    public function cancel(User $user, Reservation $reservation): bool
    {
        return $reservation->organizer_id === $user->id;
    }
}
