<?php

namespace App\Http\Resources;

use App\Models\MenuItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin MenuItem
 */
class MenuItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'restaurant_id' => $this->restaurant_id,
            'menu_category_id' => $this->menu_category_id,
            'name' => $this->name,
            'description' => $this->description,
            'price' => $this->price,
            'is_available' => $this->is_available,
            'position' => $this->position,
            'stock_quantity' => $this->stock_quantity,
            'is_sold_out' => $this->is_sold_out,
            'preparation_minutes' => $this->preparation_minutes,
            'option_groups' => MenuItemOptionGroupResource::collection($this->whenLoaded('optionGroups')),
            'allergens' => AllergenResource::collection($this->whenLoaded('allergens')),
            'photos' => $this->getMedia('photos')->map(fn ($media): array => [
                'id' => $media->id,
                'url' => $media->getUrl(),
                'thumb' => $media->getUrl('thumb'),
                'card' => $media->getUrl('card'),
            ])->all(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
