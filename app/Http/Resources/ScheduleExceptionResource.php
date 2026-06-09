<?php

namespace App\Http\Resources;

use App\Models\ScheduleException;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ScheduleException
 */
class ScheduleExceptionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'restaurant_id' => $this->restaurant_id,
            'service_id' => $this->service_id,
            'date' => $this->date?->toDateString(),
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'opens_at' => $this->opens_at,
            'last_seating_at' => $this->last_seating_at,
            'closes_at' => $this->closes_at,
            'crosses_midnight' => $this->crosses_midnight,
            'capacity_override' => $this->capacity_override,
            'pacing_override' => $this->pacing_override,
            'reason' => $this->reason,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
