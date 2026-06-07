<?php

namespace App\Http\Resources;

use App\Models\ServiceAvailability;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ServiceAvailability
 */
class ServiceAvailabilityResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'restaurant_id' => $this->restaurant_id,
            'date' => $this->date->toDateString(),
            'service_type' => $this->service_type->value,
            'capacity' => $this->capacity,
            'booked_seats' => $this->booked_seats,
            'remaining_seats' => max(0, $this->capacity - $this->booked_seats),
            'max_party_size' => $this->max_party_size,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
