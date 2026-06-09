<?php

namespace App\Actions\Reservation;

use App\Data\Reservation\InviteParticipantsData;
use App\Enums\InvitationStatus;
use App\Enums\ParticipantRole;
use App\Events\Reservation\ParticipantInvited;
use App\Exceptions\Reservation\AlreadyInvitedException;
use App\Exceptions\Reservation\ParticipantLimitExceededException;
use App\Models\FriendGroup;
use App\Models\Reservation;
use App\Models\ReservationParticipant;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InviteParticipantsAction
{
    /**
     * Invite guests to a reservation, expanding an optional friend group into its
     * members. Invitations never affect availability — the covers were claimed when
     * the reservation was created — but the total participant count is capped at
     * the reservation's party size.
     *
     * @return Collection<int, ReservationParticipant>
     */
    public function handle(Reservation $reservation, InviteParticipantsData $data, User $inviter): Collection
    {
        $candidateIds = collect($data->user_ids ?? [])->map(fn ($id): int => (int) $id);

        if ($data->friend_group_id !== null) {
            $group = FriendGroup::query()->findOrFail($data->friend_group_id);
            $candidateIds = $candidateIds->merge($group->members()->pluck('users.id'));
        }

        $candidateIds = $candidateIds->unique()->values();

        if ($candidateIds->isEmpty()) {
            throw ValidationException::withMessages([
                'user_ids' => ['There are no users to invite.'],
            ]);
        }

        // Re-invitation guard (the organizer counts as a participant, so this also
        // rejects inviting oneself); backed by the UNIQUE(reservation_id, user_id).
        if ($reservation->participants()->whereIn('user_id', $candidateIds)->exists()) {
            throw new AlreadyInvitedException;
        }

        try {
            DB::transaction(function () use ($reservation, $candidateIds): void {
                // No DB constraint enforces party_size, so lock the reservation row
                // and re-check the cap inside the transaction: concurrent invites
                // serialize here and can never push the participant count past it.
                Reservation::query()->whereKey($reservation->id)->lockForUpdate()->first();

                if ($reservation->participants()->count() + $candidateIds->count() > $reservation->party_size) {
                    throw new ParticipantLimitExceededException;
                }

                foreach ($candidateIds as $userId) {
                    $reservation->participants()->create([
                        'user_id' => $userId,
                        'role' => ParticipantRole::Guest->value,
                        'invitation_status' => InvitationStatus::Pending->value,
                    ]);
                }
            });
        } catch (UniqueConstraintViolationException) {
            // A concurrent invite slipped a duplicate past the pre-check.
            throw new AlreadyInvitedException;
        }

        $participants = $reservation->participants()
            ->whereIn('user_id', $candidateIds)
            ->with('user')
            ->get();

        ParticipantInvited::dispatch($reservation, $participants);

        return $participants;
    }
}
