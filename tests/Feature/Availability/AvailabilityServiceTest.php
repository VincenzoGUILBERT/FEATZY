<?php

use App\Enums\ScheduleExceptionType;
use App\Models\Reservation;
use App\Models\Restaurant;
use App\Models\Service;
use App\Support\Availability\AvailabilityService;
use Carbon\CarbonImmutable;

beforeEach(function () {
    CarbonImmutable::setTestNow('2026-06-15 09:00:00');
    $this->date = CarbonImmutable::parse('2026-06-20'); // +5 jours
    $this->availability = app(AvailabilityService::class);
});

afterEach(function () {
    CarbonImmutable::setTestNow();
});

/**
 * Service ouvert le jour de $this->date, fenêtre 12:00 → 13:00 (créneaux de 15 min),
 * avec les deux plafonds de couverts fournis.
 */
function lunchService(int $simultaneous = 20, int $perSlot = 8, string $pool = 'default', ?Restaurant $restaurant = null): Service
{
    $restaurant ??= Restaurant::factory()->published()->create();

    $service = Service::factory()->for($restaurant)->lunch()->create([
        'capacity_pool_key' => $pool,
        'max_simultaneous_covers' => $simultaneous,
        'max_covers_per_slot' => $perSlot,
    ]);

    foreach (range(0, 6) as $day) {
        $service->schedules()->create([
            'day_of_week' => $day,
            'opens_at' => '12:00:00',
            'last_seating_at' => '13:00:00',
            'closes_at' => '15:00:00',
            'crosses_midnight' => false,
        ]);
    }

    return $service->setRelation('restaurant', $restaurant);
}

it('generates 15-minute slots across the service window', function () {
    $service = lunchService();

    $slots = collect($this->availability->availableSlots($service, $this->date, 2))
        ->map(fn (CarbonImmutable $s) => $s->format('H:i'));

    expect($slots->all())->toBe(['12:00', '12:15', '12:30', '12:45', '13:00']);
});

it('excludes slots before the minimum lead time', function () {
    $restaurant = Restaurant::factory()->published()->create(['min_lead_time_minutes' => 240]);
    $service = lunchService(restaurant: $restaurant);

    // Date = aujourd'hui : maintenant 09:00, cutoff = 13:00 → seul 13:00 reste réservable.
    $slots = collect($this->availability->availableSlots($service, CarbonImmutable::parse('2026-06-15'), 2))
        ->map(fn (CarbonImmutable $s) => $s->format('H:i'));

    expect($slots->all())->toBe(['13:00']);
});

it('enforces pacing (max covers arriving per slot)', function () {
    $service = lunchService(simultaneous: 50, perSlot: 4);

    Reservation::factory()->forSlot($service, $this->date->setTime(12, 0), 4)->create();

    $slots = collect($this->availability->availableSlots($service, $this->date, 1))
        ->map(fn (CarbonImmutable $s) => $s->format('H:i'));

    // 12:00 est saturé en arrivées (4 + 1 > 4), les autres créneaux restent ouverts.
    expect($slots->all())->not->toContain('12:00')
        ->and($slots->all())->toContain('12:15');
});

it('enforces simultaneous covers via overlap', function () {
    $service = lunchService(simultaneous: 6, perSlot: 6); // assise 90 min

    Reservation::factory()->forSlot($service, $this->date->setTime(12, 0), 4)->create();

    // À 12:15 la résa de 12:00 occupe encore (jusqu'à 13:30) : 4 présents.
    $partyOf3 = collect($this->availability->availableSlots($service, $this->date, 3))
        ->map(fn (CarbonImmutable $s) => $s->format('H:i'));
    $partyOf2 = collect($this->availability->availableSlots($service, $this->date, 2))
        ->map(fn (CarbonImmutable $s) => $s->format('H:i'));

    expect($partyOf3->all())->not->toContain('12:15') // 4 + 3 > 6
        ->and($partyOf2->all())->toContain('12:15');   // 4 + 2 = 6
});

it('shares simultaneous capacity across services in the same pool', function () {
    $restaurant = Restaurant::factory()->published()->create();
    $serviceA = lunchService(simultaneous: 6, perSlot: 6, pool: 'salle', restaurant: $restaurant);
    $serviceB = Service::factory()->for($restaurant)->dinner()->create([
        'capacity_pool_key' => 'salle',
        'max_simultaneous_covers' => 6,
        'max_covers_per_slot' => 6,
    ]);
    $serviceB->schedules()->create([
        'day_of_week' => $this->date->dayOfWeek,
        'opens_at' => '12:00:00', 'last_seating_at' => '13:00:00', 'closes_at' => '15:00:00', 'crosses_midnight' => false,
    ]);
    $serviceB->setRelation('restaurant', $restaurant);

    // 4 couverts arrivent sur le service A à 12:00 (même pool « salle »).
    Reservation::factory()->forSlot($serviceA, $this->date->setTime(12, 0), 4)->create();

    // Le service B voit le pool occupé : 4 + 3 > 6 → 12:00 indisponible pour 3.
    $slots = collect($this->availability->availableSlots($serviceB, $this->date, 3))
        ->map(fn (CarbonImmutable $s) => $s->format('H:i'));

    expect($slots->all())->not->toContain('12:00');
});

it('returns no slots when the service is closed by an exception', function () {
    $service = lunchService();
    $service->restaurant->scheduleExceptions()->create([
        'service_id' => null,
        'date' => $this->date->toDateString(),
        'type' => ScheduleExceptionType::Closed,
    ]);

    expect($this->availability->availableSlots($service, $this->date, 2))->toBe([]);
});

it('applies special-hours exceptions to the window', function () {
    $service = lunchService();
    $service->restaurant->scheduleExceptions()->create([
        'service_id' => $service->id,
        'date' => $this->date->toDateString(),
        'type' => ScheduleExceptionType::SpecialHours,
        'opens_at' => '18:00:00', 'last_seating_at' => '18:30:00', 'closes_at' => '20:00:00', 'crosses_midnight' => false,
    ]);

    $slots = collect($this->availability->availableSlots($service, $this->date, 2))
        ->map(fn (CarbonImmutable $s) => $s->format('H:i'));

    expect($slots->all())->toBe(['18:00', '18:15', '18:30']);
});

it('applies reduced-capacity exceptions to the caps', function () {
    $service = lunchService(simultaneous: 50, perSlot: 8);
    $service->restaurant->scheduleExceptions()->create([
        'service_id' => $service->id,
        'date' => $this->date->toDateString(),
        'type' => ScheduleExceptionType::ReducedCapacity,
        'pacing_override' => 2,
    ]);

    Reservation::factory()->forSlot($service, $this->date->setTime(12, 0), 2)->create();

    $slots = collect($this->availability->availableSlots($service, $this->date, 1))
        ->map(fn (CarbonImmutable $s) => $s->format('H:i'));

    // Pacing réduit à 2 : 2 + 1 > 2 → 12:00 indisponible.
    expect($slots->all())->not->toContain('12:00');
});

it('returns no slots for a past date or beyond the horizon', function () {
    $restaurant = Restaurant::factory()->published()->create(['booking_horizon_days' => 30]);
    $service = lunchService(restaurant: $restaurant);

    expect($this->availability->availableSlots($service, CarbonImmutable::parse('2026-06-14'), 2))->toBe([])
        ->and($this->availability->availableSlots($service, CarbonImmutable::parse('2026-08-01'), 2))->toBe([]);
});
