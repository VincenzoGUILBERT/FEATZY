<?php

namespace Database\Factories;

use App\Enums\InvitationStatus;
use App\Enums\ParticipantRole;
use App\Models\Reservation;
use App\Models\ReservationParticipant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReservationParticipant>
 */
class ReservationParticipantFactory extends Factory
{
    protected $model = ReservationParticipant::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reservation_id' => Reservation::factory(),
            'user_id' => User::factory(),
            'role' => ParticipantRole::Guest,
            'invitation_status' => InvitationStatus::Pending,
            'responded_at' => null,
            'is_attending' => null,
        ];
    }

    /**
     * Indicate that the participant is the organizer of the reservation.
     */
    public function organizer(): static
    {
        return $this->state(fn (array $attributes): array => [
            'role' => ParticipantRole::Organizer,
            'invitation_status' => InvitationStatus::Accepted,
            'responded_at' => now(),
            'is_attending' => true,
        ]);
    }
}
