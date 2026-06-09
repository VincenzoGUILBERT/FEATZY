<?php

namespace App\Http\Resources;

use App\Models\Reservation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Reservation
 */
class ReservationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'public_uuid' => $this->public_uuid,
            'restaurant_id' => $this->restaurant_id,
            'service_id' => $this->service_id,
            'organizer_id' => $this->organizer_id,
            'party_size' => $this->party_size,
            'reserved_at' => $this->reserved_at?->toIso8601String(),
            'slot_at' => $this->slot_at?->toIso8601String(),
            'ends_at' => $this->ends_at?->toIso8601String(),
            'seating_duration_minutes' => $this->seating_duration_minutes,
            'capacity_pool_key' => $this->capacity_pool_key,
            'status' => $this->status->value,
            'is_preorder' => $this->is_preorder,
            'special_requests' => $this->special_requests,
            'seated_at' => $this->seated_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'cancelled_by_id' => $this->cancelled_by_id,
            'cancellation_reason' => $this->cancellation_reason,
            'restaurant' => RestaurantResource::make($this->whenLoaded('restaurant')),
            'service' => ServiceResource::make($this->whenLoaded('service')),
            'participants' => ReservationParticipantResource::collection($this->whenLoaded('participants')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
