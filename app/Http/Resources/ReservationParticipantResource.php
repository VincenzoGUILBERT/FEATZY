<?php

namespace App\Http\Resources;

use App\Models\ReservationParticipant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ReservationParticipant
 */
class ReservationParticipantResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reservation_id' => $this->reservation_id,
            'user_id' => $this->user_id,
            'role' => $this->role->value,
            'invitation_status' => $this->invitation_status->value,
            'is_attending' => $this->is_attending,
            'responded_at' => $this->responded_at?->toIso8601String(),
            'user' => UserResource::make($this->whenLoaded('user')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
