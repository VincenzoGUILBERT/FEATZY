<?php

namespace App\Support\Availability;

use App\Enums\DayOfWeek;
use App\Enums\ServiceType;
use App\Models\Restaurant;
use App\Models\ScheduleException;
use App\Models\ServiceSchedule;
use Carbon\CarbonInterface;

/**
 * Pure precedence engine: given a restaurant, a date and a service, it returns
 * the effective bookable configuration — or null when the slot is closed or
 * simply not configured. It performs no queries; the caller must eager-load
 * the restaurant's serviceSchedules and scheduleExceptions relations.
 *
 * Precedence (most specific wins): a dated same-service exception overrides a
 * whole-day exception (service_type = null), which overrides the weekly
 * schedule. A null field on the winning exception falls back to the weekly
 * schedule's value, since the weekly schedule is the canonical baseline.
 */
class AvailabilityResolver
{
    public function resolve(Restaurant $restaurant, CarbonInterface $date, ServiceType $service): ?ResolvedSlot
    {
        $weekly = $this->weeklySchedule($restaurant, $date, $service);
        $specific = $this->exception($restaurant, $date, $service);
        $wholeDay = $this->exception($restaurant, $date, null);

        if ($specific !== null) {
            if ($specific->is_closed) {
                return null;
            }

            $capacity = $specific->capacity ?? $weekly?->capacity;

            if ($capacity === null) {
                return null;
            }

            $maxPartySize = $specific->max_party_size ?? $weekly?->max_party_size;
        } elseif ($wholeDay !== null) {
            if ($wholeDay->is_closed) {
                return null;
            }

            // A whole-day override only adjusts services the weekly schedule
            // already opens; it never invents a new service.
            if ($weekly === null) {
                return null;
            }

            $capacity = $wholeDay->capacity ?? $weekly->capacity;
            $maxPartySize = $wholeDay->max_party_size ?? $weekly->max_party_size;
        } else {
            if ($weekly === null) {
                return null;
            }

            $capacity = $weekly->capacity;
            $maxPartySize = $weekly->max_party_size;
        }

        if ($maxPartySize !== null && $maxPartySize > $capacity) {
            $maxPartySize = $capacity;
        }

        return new ResolvedSlot($capacity, $maxPartySize);
    }

    private function weeklySchedule(Restaurant $restaurant, CarbonInterface $date, ServiceType $service): ?ServiceSchedule
    {
        $day = DayOfWeek::from($date->dayOfWeek);

        return $restaurant->serviceSchedules
            ->first(fn (ServiceSchedule $schedule): bool => $schedule->is_active
                && $schedule->day_of_week === $day
                && $schedule->service_type === $service);
    }

    private function exception(Restaurant $restaurant, CarbonInterface $date, ?ServiceType $service): ?ScheduleException
    {
        return $restaurant->scheduleExceptions
            ->first(fn (ScheduleException $exception): bool => $exception->date->isSameDay($date)
                && $exception->service_type === $service);
    }
}
