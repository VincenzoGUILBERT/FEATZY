<?php

namespace App\Models;

use App\Enums\DayOfWeek;
use Database\Factories\ServiceScheduleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Fenêtre hebdomadaire récurrente d'ouverture d'un service un jour donné. Plusieurs
 * fenêtres par (service, jour) sont permises pour les services coupés.
 */
class ServiceSchedule extends Model
{
    /** @use HasFactory<ServiceScheduleFactory> */
    use HasFactory;

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'day_of_week' => DayOfWeek::class,
            'crosses_midnight' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Service, $this>
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }
}
