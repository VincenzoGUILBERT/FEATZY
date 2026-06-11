<?php

namespace App\Http\Resources;

use App\Models\Restaurant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Restaurant
 */
class RestaurantResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'owner_id' => $this->owner_id,
            'name' => $this->name,
            'description' => $this->description,
            'email' => $this->email,
            'phone' => $this->phone,
            'address' => [
                'street' => $this->street,
                'postal_code' => $this->postal_code,
                'city' => $this->city,
                'latitude' => $this->latitude !== null ? (float) $this->latitude : null,
                'longitude' => $this->longitude !== null ? (float) $this->longitude : null,
            ],
            'price_level' => $this->price_level?->value,
            'accepts_preorders' => $this->accepts_preorders,
            'accepts_online_payment' => $this->accepts_online_payment,
            'cancellation_deadline_hours' => $this->cancellation_deadline_hours,
            'booking_horizon_days' => $this->booking_horizon_days,
            'status' => $this->status->value,
            'average_rating' => $this->average_rating !== null ? (float) $this->average_rating : null,
            'reviews_count' => $this->reviews_count,
            'is_favorited' => $this->whenHas('is_favorited', fn (): bool => (bool) $this->is_favorited),
            'cuisine_types' => CuisineTypeResource::collection($this->whenLoaded('cuisineTypes')),
            'opening_hours' => $this->whenLoaded('services', fn () => $this->services
                ->where('is_active', true)
                ->flatMap(fn ($service) => $service->schedules->map(fn ($schedule): array => [
                    'day_of_week' => $schedule->day_of_week->value,
                    'day_name' => $schedule->day_of_week->label(),
                    'opens_at' => $schedule->opens_at,
                    'last_seating_at' => $schedule->last_seating_at,
                    'closes_at' => $schedule->closes_at,
                    'crosses_midnight' => $schedule->crosses_midnight,
                    'service_name' => $service->name,
                ]))
                ->sortBy(['day_of_week', 'opens_at'])
                ->values()
                ->all()),
            'media' => [
                'logo' => $this->getFirstMediaUrl('logo') ?: null,
                'cover' => $this->getFirstMediaUrl('cover') ?: null,
                'gallery' => $this->getMedia('gallery')->map(fn ($media): string => $media->getUrl())->all(),
            ],
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
