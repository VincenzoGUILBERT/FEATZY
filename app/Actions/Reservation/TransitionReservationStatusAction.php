<?php

namespace App\Actions\Reservation;

use App\Enums\ReservationStatus;
use App\Exceptions\InvalidStatusTransitionException;
use App\Models\Reservation;

class TransitionReservationStatusAction
{
    /**
     * Move a reservation along the restaurant-side lifecycle (seat / complete /
     * no-show). The transition is a conditional UPDATE constrained to the legal
     * source statuses, so concurrent board actions can never apply an illegal one.
     * no-show deliberately does NOT restore capacity; cancellation (which does)
     * goes through CancelReservationAction.
     */
    public function handle(Reservation $reservation, ReservationStatus $to): Reservation
    {
        $allowed = $this->allowedSources($to);

        if (! in_array($reservation->status, $allowed, true)) {
            throw InvalidStatusTransitionException::between($reservation->status->value, $to->value);
        }

        $attributes = ['status' => $to->value];

        if ($to === ReservationStatus::Seated) {
            $attributes['seated_at'] = now();
        }

        if ($to === ReservationStatus::Completed) {
            $attributes['completed_at'] = now();
        }

        $transitioned = Reservation::query()
            ->whereKey($reservation->id)
            ->whereIn('status', array_map(fn (ReservationStatus $status): string => $status->value, $allowed))
            ->update($attributes);

        if ($transitioned === 0) {
            throw InvalidStatusTransitionException::between($reservation->status->value, $to->value);
        }

        $reservation->refresh();

        return $reservation->load(['service', 'participants.user']);
    }

    /**
     * @return array<int, ReservationStatus>
     */
    private function allowedSources(ReservationStatus $to): array
    {
        return match ($to) {
            ReservationStatus::Seated => [ReservationStatus::Confirmed],
            ReservationStatus::Completed => [ReservationStatus::Seated],
            ReservationStatus::NoShow => [ReservationStatus::Confirmed, ReservationStatus::Seated],
            default => [],
        };
    }
}
