<?php

namespace App\Http\Resources;

use App\Models\Allergen;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Allergen
 */
class AllergenResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'icon' => $this->icon,
            'position' => $this->position,
            'menu_items_count' => $this->whenCounted('menuItems'),
        ];
    }
}
