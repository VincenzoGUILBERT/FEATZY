<?php

namespace App\Http\Resources;

use App\Models\MenuItemOption;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin MenuItemOption
 */
class MenuItemOptionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'option_group_id' => $this->option_group_id,
            'name' => $this->name,
            'price_delta' => $this->price_delta,
            'stock_quantity' => $this->stock_quantity,
            'is_sold_out' => $this->is_sold_out,
            'is_available' => $this->is_available,
            'position' => $this->position,
        ];
    }
}
