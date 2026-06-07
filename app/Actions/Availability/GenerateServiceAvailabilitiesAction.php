<?php

namespace App\Actions\Availability;

use App\Data\Availability\GenerateAvailabilitiesData;
use App\Enums\ServiceType;
use App\Models\Restaurant;
use App\Models\ServiceAvailability;
use App\Support\Availability\AvailabilityResolver;
use App\Support\Availability\GenerationResult;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\DB;

class GenerateServiceAvailabilitiesAction
{
    public function __construct(private readonly AvailabilityResolver $resolver) {}

    /**
     * Materialise schedules + exceptions into service_availabilities rows over a
     * date range, clamped to [today, today + booking_horizon_days]. The upsert
     * is idempotent: it never resets booked_seats and never lowers capacity below
     * it (which would breach the CHECK constraint).
     */
    public function handle(Restaurant $restaurant, GenerateAvailabilitiesData $data): GenerationResult
    {
        $today = CarbonImmutable::today();
        $horizonEnd = $today->addDays($restaurant->booking_horizon_days);

        $from = $data->from !== null ? CarbonImmutable::parse($data->from)->startOfDay() : $today;
        $to = $data->to !== null ? CarbonImmutable::parse($data->to)->startOfDay() : $horizonEnd;

        // Never regenerate the past, never exceed the booking horizon.
        $from = $from->lessThan($today) ? $today : $from;
        $to = $to->greaterThan($horizonEnd) ? $horizonEnd : $to;

        if ($from->greaterThan($to)) {
            return new GenerationResult($from->toDateString(), $to->toDateString(), 0, 0, 0, 0);
        }

        $restaurant->load([
            'serviceSchedules' => fn ($query) => $query->where('is_active', true),
            'scheduleExceptions' => fn ($query) => $query->whereBetween('date', [$from->toDateString(), $to->toDateString()]),
        ]);

        $created = $updated = $clamped = $deleted = 0;

        DB::transaction(function () use ($restaurant, $from, $to, &$created, &$updated, &$clamped, &$deleted): void {
            foreach (CarbonPeriod::create($from, $to) as $date) {
                foreach (ServiceType::cases() as $service) {
                    $slot = $this->resolver->resolve($restaurant, $date, $service);

                    $existing = ServiceAvailability::query()
                        ->where('restaurant_id', $restaurant->id)
                        ->where('date', $date->toDateString())
                        ->where('service_type', $service->value)
                        ->first();

                    if ($slot === null) {
                        // A now-closed slot is removed only when it has no bookings.
                        // Booked rows are left untouched for the reservation flow to
                        // settle (Session 8); generation never mutates booked seats.
                        if ($existing !== null && $existing->booked_seats === 0) {
                            $existing->delete();
                            $deleted++;
                        }

                        continue;
                    }

                    if ($existing === null) {
                        ServiceAvailability::query()->create([
                            'restaurant_id' => $restaurant->id,
                            'date' => $date->toDateString(),
                            'service_type' => $service->value,
                            'capacity' => $slot->capacity,
                            'booked_seats' => 0,
                            'max_party_size' => $slot->max_party_size,
                        ]);
                        $created++;

                        continue;
                    }

                    $wasClamped = $slot->capacity < $existing->booked_seats;
                    $capacity = max($slot->capacity, $existing->booked_seats);

                    $maxPartySize = $slot->max_party_size;
                    if ($maxPartySize !== null && $maxPartySize > $capacity) {
                        $maxPartySize = $capacity;
                    }

                    if ($existing->capacity !== $capacity || $existing->max_party_size !== $maxPartySize) {
                        $existing->update([
                            'capacity' => $capacity,
                            'max_party_size' => $maxPartySize,
                        ]);
                        $updated++;

                        if ($wasClamped) {
                            $clamped++;
                        }
                    }
                }
            }
        });

        return new GenerationResult($from->toDateString(), $to->toDateString(), $created, $updated, $clamped, $deleted);
    }
}
