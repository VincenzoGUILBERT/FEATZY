<?php

namespace App\Actions\Reservation;

use App\Data\Reservation\CreateReservationData;
use App\Enums\InvitationStatus;
use App\Enums\ParticipantRole;
use App\Enums\ReservationStatus;
use App\Events\Reservation\ReservationCreated;
use App\Exceptions\Order\PreordersNotAcceptedException;
use App\Exceptions\Reservation\CapacityExceededException;
use App\Models\Reservation;
use App\Models\Restaurant;
use App\Models\ServiceAvailability;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreateReservationAction
{
    /**
     * Book a service slot for the organizer, decrementing capacity atomically.
     *
     * The capacity guard is a single conditional UPDATE
     * (`booked_seats + party <= capacity`) rather than a read-then-write, so
     * concurrent bookings can never overshoot the slot's capacity; a zero-row
     * result means the slot is full and surfaces as a 409. The organizer is
     * persisted as the first participant within the same transaction.
     */
    public function handle(Restaurant $restaurant, CreateReservationData $data, User $organizer): Reservation
    {
        if ($data->is_preorder && ! $restaurant->accepts_preorders) {
            throw new PreordersNotAcceptedException;
        }

        $slot = ServiceAvailability::query()->findOrFail($data->service_availability_id);
        $partySize = $data->party_size;

        $reservation = DB::transaction(function () use ($restaurant, $slot, $data, $organizer, $partySize): Reservation {
            $seatsTaken = ServiceAvailability::query()
                ->whereKey($slot->id)
                ->whereRaw('booked_seats + ? <= capacity', [$partySize])
                ->update(['booked_seats' => DB::raw('booked_seats + '.$partySize)]);

            if ($seatsTaken === 0) {
                throw new CapacityExceededException;
            }

            $reservation = $restaurant->reservations()->create([
                'service_availability_id' => $slot->id,
                'organizer_id' => $organizer->id,
                'reservation_date' => $slot->date->toDateString(),
                'service_type' => $slot->service_type->value,
                'party_size' => $partySize,
                'status' => ReservationStatus::Confirmed->value,
                'is_preorder' => $data->is_preorder,
                'special_requests' => $data->special_requests,
                'expected_arrival_time' => $data->expected_arrival_time,
            ]);

            $reservation->participants()->create([
                'user_id' => $organizer->id,
                'role' => ParticipantRole::Organizer->value,
                'invitation_status' => InvitationStatus::Accepted->value,
                'responded_at' => now(),
                'is_attending' => true,
            ]);

            return $reservation;
        });

        ReservationCreated::dispatch($reservation);

        return $reservation->load(['participants.user', 'serviceAvailability']);
    }
}
