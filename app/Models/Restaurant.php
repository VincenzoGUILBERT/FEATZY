<?php

namespace App\Models;

use App\Enums\PriceLevel;
use App\Enums\RestaurantStatus;
use Database\Factories\RestaurantFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Restaurant extends Model implements HasMedia
{
    /** @use HasFactory<RestaurantFactory> */
    use HasFactory, InteractsWithMedia, SoftDeletes;

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price_level' => PriceLevel::class,
            'status' => RestaurantStatus::class,
            'accepts_preorders' => 'boolean',
            'accepts_online_payment' => 'boolean',
            'cancellation_deadline_hours' => 'integer',
            'booking_horizon_days' => 'integer',
            'default_seating_duration_minutes' => 'integer',
            'slot_interval_minutes' => 'integer',
            'min_lead_time_minutes' => 'integer',
            'min_party_size' => 'integer',
            'max_party_size' => 'integer',
            'reviews_count' => 'integer',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'average_rating' => 'decimal:2',
        ];
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('logo')->singleFile();
        $this->addMediaCollection('cover')->singleFile();
        $this->addMediaCollection('gallery');
    }

    /**
     * @param  Builder<Restaurant>  $query
     */
    public function scopePublished(Builder $query): void
    {
        $query->where('status', RestaurantStatus::Published->value);
    }

    /**
     * Restrict to restaurants within $radiusKm of a point and expose a `distance`
     * (km) column for sorting; restaurants without coordinates are excluded.
     *
     * @param  Builder<Restaurant>  $query
     */
    public function scopeNearby(Builder $query, float $latitude, float $longitude, ?float $radiusKm = null): void
    {
        $haversine = '(6371 * acos(least(1.0, cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude)))))';

        $query->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->selectRaw("restaurants.*, {$haversine} as distance", [$latitude, $longitude, $latitude]);

        if ($radiusKm !== null) {
            $query->whereRaw("{$haversine} <= ?", [$latitude, $longitude, $latitude, $radiusKm]);
        }
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * @return BelongsToMany<CuisineType, $this>
     */
    public function cuisineTypes(): BelongsToMany
    {
        return $this->belongsToMany(CuisineType::class)->withTimestamps();
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function usersWhoFavorited(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'favorites')->withTimestamps();
    }

    /**
     * @return HasMany<Favorite, $this>
     */
    public function favorites(): HasMany
    {
        return $this->hasMany(Favorite::class);
    }

    /**
     * @return HasMany<Review, $this>
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    /**
     * @return HasMany<Reservation, $this>
     */
    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    /**
     * @return HasMany<MenuCategory, $this>
     */
    public function menuCategories(): HasMany
    {
        return $this->hasMany(MenuCategory::class);
    }

    /**
     * @return HasMany<MenuItem, $this>
     */
    public function menuItems(): HasMany
    {
        return $this->hasMany(MenuItem::class);
    }

    /**
     * @return HasMany<Service, $this>
     */
    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }

    /**
     * Horaires de tous les services du restaurant (traversant la table services).
     *
     * @return HasManyThrough<ServiceSchedule, Service, $this>
     */
    public function serviceSchedules(): HasManyThrough
    {
        return $this->hasManyThrough(ServiceSchedule::class, Service::class);
    }

    /**
     * @return HasMany<ScheduleException, $this>
     */
    public function scheduleExceptions(): HasMany
    {
        return $this->hasMany(ScheduleException::class);
    }

    /**
     * @return HasMany<Order, $this>
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}
