<?php

namespace App\Models;

use App\Enums\ReservationStatus;
use App\Observers\ReservationObserver;
use Carbon\CarbonInterface;
use Database\Factories\ReservationFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Réservation par créneau. Cœur transactionnel : `reserved_at` (arrivée), `slot_at`
 * (bucket d'arrivée aligné sur la grille → pacing par simple égalité) et `ends_at`
 * (départ) sont des datetimes locaux (mono-fuseau). `seating_duration_minutes` et
 * `capacity_pool_key` sont des snapshots figés à la création.
 */
#[ObservedBy(ReservationObserver::class)]
class Reservation extends Model
{
    /** @use HasFactory<ReservationFactory> */
    use HasFactory, SoftDeletes;

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'party_size' => 'integer',
            'reserved_at' => 'datetime',
            'slot_at' => 'datetime',
            'ends_at' => 'datetime',
            'seating_duration_minutes' => 'integer',
            'status' => ReservationStatus::class,
            'is_preorder' => 'boolean',
            'seated_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    /**
     * Aligne un instant sur la grille absolue de créneaux (multiples de $interval
     * minutes depuis minuit), si bien que deux arrivées du même bucket partagent un
     * `slot_at` identique : le pacing se réduit à un filtre d'égalité.
     */
    public static function alignToGrid(CarbonInterface $instant, int $interval): CarbonInterface
    {
        $minutesIntoDay = $instant->hour * 60 + $instant->minute;
        $aligned = intdiv($minutesIntoDay, $interval) * $interval;

        return $instant->copy()->startOfDay()->addMinutes($aligned);
    }

    /**
     * @return BelongsTo<Restaurant, $this>
     */
    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    /**
     * @return BelongsTo<Service, $this>
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function organizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'organizer_id');
    }

    /**
     * @return HasMany<ReservationParticipant, $this>
     */
    public function participants(): HasMany
    {
        return $this->hasMany(ReservationParticipant::class);
    }

    /**
     * @return HasOne<Order, $this>
     */
    public function order(): HasOne
    {
        return $this->hasOne(Order::class);
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * @return HasMany<Review, $this>
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    /**
     * Réservations consommant de la capacité (confirmées ou installées).
     *
     * @param  Builder<Reservation>  $query
     */
    public function scopeOccupying(Builder $query): void
    {
        $query->whereIn('status', ReservationStatus::occupyingCapacity());
    }

    /**
     * @param  Builder<Reservation>  $query
     */
    public function scopeInPool(Builder $query, string $capacityPoolKey): void
    {
        $query->where('capacity_pool_key', $capacityPoolKey);
    }

    /**
     * Réservations dont l'occupation [reserved_at, ends_at) chevauche [start, end).
     *
     * @param  Builder<Reservation>  $query
     */
    public function scopeOverlapping(Builder $query, CarbonInterface $start, CarbonInterface $end): void
    {
        $query->where('reserved_at', '<', $end)->where('ends_at', '>', $start);
    }

    /**
     * Réservations arrivant exactement sur un bucket de pacing donné.
     *
     * @param  Builder<Reservation>  $query
     */
    public function scopeArrivingInBucket(Builder $query, CarbonInterface $bucketStart): void
    {
        $query->where('slot_at', $bucketStart);
    }
}
