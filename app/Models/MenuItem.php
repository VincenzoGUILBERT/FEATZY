<?php

namespace App\Models;

use Database\Factories\MenuItemFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class MenuItem extends Model implements HasMedia
{
    /** @use HasFactory<MenuItemFactory> */
    use HasFactory, InteractsWithMedia, SoftDeletes;

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
            'price' => 'integer',
            'is_available' => 'boolean',
            'position' => 'integer',
            'stock_quantity' => 'integer',
            'preparation_minutes' => 'integer',
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

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('photos');
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->fit(Fit::Contain, 300, 300)
            ->nonQueued();

        $this->addMediaConversion('card')
            ->fit(Fit::Crop, 600, 400)
            ->nonQueued();
    }

    /**
     * @return BelongsTo<Restaurant, $this>
     */
    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    /**
     * @return BelongsTo<MenuCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(MenuCategory::class, 'menu_category_id');
    }

    /**
     * @return HasMany<MenuItemOptionGroup, $this>
     */
    public function optionGroups(): HasMany
    {
        return $this->hasMany(MenuItemOptionGroup::class);
    }

    /**
     * @return BelongsToMany<Allergen, $this>
     */
    public function allergens(): BelongsToMany
    {
        return $this->belongsToMany(Allergen::class);
    }
}
