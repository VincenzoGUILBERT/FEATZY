<?php

namespace App\Models;

use App\Enums\ScheduleExceptionType;
use Database\Factories\ScheduleExceptionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Dérogation datée d'un restaurant : fermeture, horaires spéciaux ou capacité réduite.
 * `service_id` null = dérogation pour tout le restaurant ; un service ciblé prime.
 */
class ScheduleException extends Model
{
    /** @use HasFactory<ScheduleExceptionFactory> */
    use HasFactory;

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'type' => ScheduleExceptionType::class,
            'crosses_midnight' => 'boolean',
            'capacity_override' => 'integer',
            'pacing_override' => 'integer',
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
     * @return BelongsTo<Service, $this>
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }
}
