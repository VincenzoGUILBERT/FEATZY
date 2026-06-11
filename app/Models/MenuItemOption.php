<?php

namespace App\Models;

use Database\Factories\MenuItemOptionFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MenuItemOption extends Model
{
    /** @use HasFactory<MenuItemOptionFactory> */
    use HasFactory;

    protected $guarded = [];

    /**
     * @var list<string>
     */
    protected $appends = ['is_sold_out'];

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
     * Derived sold-out flag: a tracked stock that has run out.
     * A null stock means untracked/unlimited, never sold out.
     *
     * @return Attribute<bool, never>
     */
    protected function isSoldOut(): Attribute
    {
        return Attribute::make(
            get: fn (): bool => $this->stock_quantity !== null && $this->stock_quantity <= 0,
        )->shouldCache();
    }

    /**
     * @return BelongsTo<MenuItemOptionGroup, $this>
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(MenuItemOptionGroup::class, 'option_group_id');
    }
}
