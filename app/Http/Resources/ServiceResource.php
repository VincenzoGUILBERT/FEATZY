<?php

namespace App\Http\Resources;

use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Service
 */
class ServiceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'restaurant_id' => $this->restaurant_id,
            'name' => $this->name,
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'capacity_pool_key' => $this->capacity_pool_key,
            'max_simultaneous_covers' => $this->max_simultaneous_covers,
            'max_covers_per_slot' => $this->max_covers_per_slot,
            'seating_duration_minutes' => $this->seating_duration_minutes,
            'slot_interval_minutes' => $this->slot_interval_minutes,
            'min_party_size' => $this->min_party_size,
            'max_party_size' => $this->max_party_size,
            'position' => $this->position,
            'is_active' => $this->is_active,
            'schedules' => ServiceScheduleResource::collection($this->whenLoaded('schedules')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
