<?php

namespace App\Models;

use Database\Factories\MenuItemOptionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MenuItemOption extends Model
{
    /** @use HasFactory<MenuItemOptionFactory> */
    use HasFactory;

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price_delta' => 'integer',
            'stock_quantity' => 'integer',
            'is_available' => 'boolean',
            'position' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<MenuItemOptionGroup, $this>
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(MenuItemOptionGroup::class, 'option_group_id');
    }
}
