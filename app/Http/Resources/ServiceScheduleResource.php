<?php

namespace App\Http\Resources;

use App\Models\ServiceSchedule;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ServiceSchedule
 */
class ServiceScheduleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'service_id' => $this->service_id,
            'day_of_week' => $this->day_of_week->value,
            'opens_at' => $this->opens_at,
            'last_seating_at' => $this->last_seating_at,
            'closes_at' => $this->closes_at,
            'crosses_midnight' => $this->crosses_midnight,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
