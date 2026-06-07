<?php

namespace App\Support\Availability;

/**
 * Outcome summary of a generation run over a date range. `clamped` is a subset
 * of `updated` (rows whose resolved capacity was raised to honour existing
 * booked_seats); `deleted` rows are now-closed slots that had no bookings.
 */
final readonly class GenerationResult
{
    public function __construct(
        public string $from,
        public string $to,
        public int $created,
        public int $updated,
        public int $clamped,
        public int $deleted,
    ) {}

    /**
     * @return array<string, string|int>
     */
    public function toArray(): array
    {
        return [
            'from' => $this->from,
            'to' => $this->to,
            'created' => $this->created,
            'updated' => $this->updated,
            'clamped' => $this->clamped,
            'deleted' => $this->deleted,
        ];
    }
}
