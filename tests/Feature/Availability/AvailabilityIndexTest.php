<?php

use App\Enums\ScheduleExceptionType;
use App\Models\Reservation;
use App\Models\Restaurant;
use App\Models\Service;
use Carbon\CarbonImmutable;

beforeEach(function () {
    $this->withHeader('Origin', config('app.frontend_url'));
    CarbonImmutable::setTestNow('2026-06-15 09:00:00');

    $this->date = '2026-06-20'; // +5 jours, dans l'horizon de réservation
});

afterEach(function () {
    CarbonImmutable::setTestNow();
});

/**
 * Crée un service déjeuner ouvert le jour de $this->date (fenêtre 12:00 → 13:30,
 * créneaux de 15 min) avec les deux plafonds de couverts fournis.
 */
function availabilityService(Restaurant $restaurant, int $simultaneous = 40, int $perSlot = 8): Service
{
    $service = Service::factory()->for($restaurant)->lunch()->create([
        'max_simultaneous_covers' => $simultaneous,
        'max_covers_per_slot' => $perSlot,
    ]);

    $service->schedules()->create([
        'day_of_week' => CarbonImmutable::parse('2026-06-20')->dayOfWeek,
        'opens_at' => '12:00:00',
        'last_seating_at' => '13:30:00',
        'closes_at' => '15:00:00',
        'crosses_midnight' => false,
    ]);

    return $service;
}

it('returns services and their bookable slots for a published restaurant', function () {
    $restaurant = Restaurant::factory()->published()->create();
    $service = availabilityService($restaurant);

    $response = $this->getJson("/api/restaurants/{$restaurant->id}/availability?date={$this->date}&party_size=2")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.service.id', $service->id)
        ->assertJsonPath('data.0.service.name', $service->name)
        ->assertJsonPath('data.0.date', $this->date);

    $slots = $response->json('data.0.slots');

    expect($slots)->not->toBeEmpty()
        ->and($slots[0]['reserved_at'])->toMatch('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/')
        ->and($slots[0]['time'])->toMatch('/^\d{2}:\d{2}$/')
        ->and($slots[0]['reserved_at'])->toBe("{$this->date} 12:00:00")
        ->and($slots[0]['time'])->toBe('12:00');
});

it('returns 404 for a restaurant that is not published', function () {
    $restaurant = Restaurant::factory()->create(); // brouillon
    availabilityService($restaurant);

    $this->getJson("/api/restaurants/{$restaurant->id}/availability?date={$this->date}&party_size=2")
        ->assertNotFound();
});

it('validates that date and party_size are required', function () {
    $restaurant = Restaurant::factory()->published()->create();
    availabilityService($restaurant);

    $this->getJson("/api/restaurants/{$restaurant->id}/availability")
        ->assertStatus(422)
        ->assertJsonValidationErrors(['date', 'party_size']);
});

it('reflects pacing: a slot saturated by an existing reservation disappears', function () {
    $restaurant = Restaurant::factory()->published()->create();
    $service = availabilityService($restaurant, simultaneous: 40, perSlot: 4);

    // Le créneau 12:00 est saturé en arrivées (4 couverts = max_covers_per_slot).
    Reservation::factory()->forSlot($service, CarbonImmutable::parse('2026-06-20 12:00:00'), 4)->create();

    $response = $this->getJson("/api/restaurants/{$restaurant->id}/availability?date={$this->date}&party_size=2")
        ->assertOk();

    $times = collect($response->json('data.0.slots'))->pluck('time')->all();

    // 4 + 2 > 4 → 12:00 retiré, les autres créneaux restent ouverts.
    expect($times)->not->toContain('12:00')
        ->and($times)->toContain('12:15');
});

it('returns empty slots for a service closed by an exception', function () {
    $restaurant = Restaurant::factory()->published()->create();
    $service = availabilityService($restaurant);

    $restaurant->scheduleExceptions()->create([
        'service_id' => null, // dérogation restaurant-wide
        'date' => $this->date,
        'type' => ScheduleExceptionType::Closed,
    ]);

    $response = $this->getJson("/api/restaurants/{$restaurant->id}/availability?date={$this->date}&party_size=2")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.service.id', $service->id);

    expect($response->json('data.0.slots'))->toBe([]);
});

it('filters by service_id to return a single service', function () {
    $restaurant = Restaurant::factory()->published()->create();
    $lunch = availabilityService($restaurant);

    $dinner = Service::factory()->for($restaurant)->dinner()->create([
        'max_simultaneous_covers' => 40,
        'max_covers_per_slot' => 8,
    ]);
    $dinner->schedules()->create([
        'day_of_week' => CarbonImmutable::parse('2026-06-20')->dayOfWeek,
        'opens_at' => '19:00:00',
        'last_seating_at' => '21:00:00',
        'closes_at' => '23:00:00',
        'crosses_midnight' => false,
    ]);

    $this->getJson("/api/restaurants/{$restaurant->id}/availability?date={$this->date}&party_size=2&service_id={$lunch->id}")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.service.id', $lunch->id);
});
