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
            'service_availability_id' => $this->service_availability_id,
            'organizer_id' => $this->organizer_id,
            'reservation_date' => $this->reservation_date->toDateString(),
            'service_type' => $this->service_type->value,
            'party_size' => $this->party_size,
            'status' => $this->status->value,
            'is_preorder' => $this->is_preorder,
            'special_requests' => $this->special_requests,
            'expected_arrival_time' => $this->expected_arrival_time,
            'seated_at' => $this->seated_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'cancelled_by_id' => $this->cancelled_by_id,
            'cancellation_reason' => $this->cancellation_reason,
            'restaurant' => RestaurantResource::make($this->whenLoaded('restaurant')),
            'service_availability' => ServiceAvailabilityResource::make($this->whenLoaded('serviceAvailability')),
            'participants' => ReservationParticipantResource::collection($this->whenLoaded('participants')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
