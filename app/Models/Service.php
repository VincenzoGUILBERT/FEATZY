<?php

namespace App\Models;

use App\Enums\ServiceType;
use Database\Factories\ServiceFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Service d'un restaurant (Déjeuner, Dîner, Brunch…). Porte les deux plafonds de
 * couverts — `max_simultaneous_covers` (occupation de la salle) et
 * `max_covers_per_slot` (pacing / lissage des arrivées) — et peut surcharger la durée
 * d'assise, la granularité de créneau et les bornes de groupe héritées du restaurant.
 *
 * `max_simultaneous_covers` s'interprète au niveau du POOL (restaurant_id +
 * capacity_pool_key) : des services partageant la même salle physique partagent la clé
 * et additionnent leurs couverts présents, afin de ne pas sur-réserver la salle réelle.
 */
class Service extends Model
{
    /** @use HasFactory<ServiceFactory> */
    use HasFactory, SoftDeletes;

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ServiceType::class,
            'max_simultaneous_covers' => 'integer',
            'max_covers_per_slot' => 'integer',
            'seating_duration_minutes' => 'integer',
            'slot_interval_minutes' => 'integer',
            'min_party_size' => 'integer',
            'max_party_size' => 'integer',
            'position' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Restaurant, $this>
     */
    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    /**
     * @return HasMany<ServiceSchedule, $this>
     */
    public function schedules(): HasMany
    {
        return $this->hasMany(ServiceSchedule::class);
    }

    /**
     * @return HasMany<ScheduleException, $this>
     */
    public function scheduleExceptions(): HasMany
    {
        return $this->hasMany(ScheduleException::class);
    }

    /**
     * @return HasMany<Reservation, $this>
     */
    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    /**
     * Durée d'occupation effective (override du service, sinon défaut du restaurant).
     */
    public function effectiveSeatingDuration(): int
    {
        return $this->seating_duration_minutes ?? $this->restaurant->default_seating_duration_minutes;
    }

    /**
     * Granularité de créneau effective (override du service, sinon défaut du restaurant).
     */
    public function effectiveSlotInterval(): int
    {
        return $this->slot_interval_minutes ?? $this->restaurant->slot_interval_minutes;
    }

    /**
     * Taille de groupe minimale effective (override du service, sinon défaut du restaurant).
     */
    public function effectiveMinPartySize(): int
    {
        return $this->min_party_size ?? $this->restaurant->min_party_size;
    }

    /**
     * Taille de groupe maximale effective (override du service, sinon défaut du restaurant).
     */
    public function effectiveMaxPartySize(): int
    {
        return $this->max_party_size ?? $this->restaurant->max_party_size;
    }

    /**
     * @param  Builder<Service>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }
}
