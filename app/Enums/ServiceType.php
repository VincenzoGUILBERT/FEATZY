<?php

namespace App\Enums;

enum ServiceType: string
{
    case Lunch = 'lunch';
    case Dinner = 'dinner';

    /**
     * Representative start time of the service. The slot abstraction does not
     * store a precise service time, so this anchors time-relative business rules
     * (e.g. the cancellation deadline) to a sensible per-service moment.
     */
    public function representativeStartTime(): string
    {
        return match ($this) {
            self::Lunch => '12:00:00',
            self::Dinner => '19:00:00',
        };
    }
}
