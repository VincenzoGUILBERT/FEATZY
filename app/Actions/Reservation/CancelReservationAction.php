<?php

namespace App\Actions\Reservation;

use App\Enums\ReservationStatus;
use App\Events\Reservation\ReservationCancelled;
use App\Exceptions\InvalidStatusTransitionException;
use App\Exceptions\Reservation\CancellationDeadlinePassedException;
use App\Models\Reservation;
use App\Models\ServiceAvailability;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CancelReservationAction
{
    /**
     * Cancel a confirmed reservation within its deadline and return its seats to
     * the slot. The confirmed → cancelled transition is itself a conditional
     * UPDATE (WHERE status = confirmed) so a concurrent or replayed cancel can
     * never restore the same seats twice. The restore is clamped via LEAST so it
     * can never underflow the UNSIGNED column.
     */
    public function handle(Reservation $reservation, User $cancelledBy, ?string $reason = null): Reservation
    {
        if ($reservation->status !== ReservationStatus::Confirmed) {
            throw InvalidStatusTransitionException::between(
                $reservation->status->value,
                ReservationStatus::Cancelled->value,
            );
        }

        $reservation->loadMissing('restaurant');

        $serviceStart = $reservation->reservation_date
            ->copy()
            ->setTimeFromTimeString($reservation->service_type->representativeStartTime());

        $deadline = $serviceStart->subHours($reservation->restaurant->cancellation_deadline_hours);

        if (now()->greaterThan($deadline)) {
            throw new CancellationDeadlinePassedException;
        }

        DB::transaction(function () use ($reservation, $cancelledBy, $reason): void {
            $transitioned = Reservation::query()
                ->whereKey($reservation->id)
                ->where('status', ReservationStatus::Confirmed->value)
                ->update([
                    'status' => ReservationStatus::Cancelled->value,
                    'cancelled_at' => now(),
                    'cancelled_by_id' => $cancelledBy->id,
                    'cancellation_reason' => $reason,
                ]);

            // A concurrent cancel already moved the row out of "confirmed": skip
            // the seat restore so capacity is never released more than once.
            if ($transitioned === 0) {
                throw InvalidStatusTransitionException::between(
                    $reservation->status->value,
                    ReservationStatus::Cancelled->value,
                );
            }

            ServiceAvailability::query()
                ->whereKey($reservation->service_availability_id)
                ->update(['booked_seats' => DB::raw('booked_seats - LEAST(booked_seats, '.$reservation->party_size.')')]);
        });

        $reservation->refresh();

        ReservationCancelled::dispatch($reservation, $cancelledBy);

        return $reservation->load(['participants.user', 'serviceAvailability']);
    }
}
