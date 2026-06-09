<?php

use App\Enums\ReservationStatus;
use App\Events\Reservation\ReservationCreated;
use App\Models\Reservation;
use App\Models\Restaurant;
use App\Models\Service;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    $this->withHeader('Origin', config('app.frontend_url'));
    CarbonImmutable::setTestNow('2026-06-15 09:00:00');
});

afterEach(function () {
    CarbonImmutable::setTestNow();
});

/**
 * Crée un service ouvert le jour de $date avec une fenêtre alignée, afin que
 * reserved_at (12:00) soit un créneau candidat.
 */
function bookableService(Restaurant $restaurant, CarbonImmutable $date, int $simultaneous = 40, int $perSlot = 8): Service
{
    $service = Service::factory()->for($restaurant)->lunch()->create([
        'max_simultaneous_covers' => $simultaneous,
        'max_covers_per_slot' => $perSlot,
    ]);

    $service->schedules()->create([
        'day_of_week' => $date->dayOfWeek,
        'opens_at' => '12:00:00',
        'last_seating_at' => '13:30:00',
        'closes_at' => '15:00:00',
        'crosses_midnight' => false,
    ]);

    return $service->setRelation('restaurant', $restaurant);
}

it('books a valid slot and creates the organizer participant', function () {
    Event::fake([ReservationCreated::class]);

    $user = actingAsClient();
    $restaurant = Restaurant::factory()->published()->create();
    $date = CarbonImmutable::parse('2026-06-20');
    $service = bookableService($restaurant, $date);

    $this->postJson("/api/restaurants/{$restaurant->id}/reservations", [
        'service_id' => $service->id,
        'date' => '2026-06-20',
        'reserved_at' => '2026-06-20 12:00:00',
        'party_size' => 4,
        'special_requests' => 'Table près de la fenêtre',
    ])
        ->assertCreated()
        ->assertJsonPath('data.status', ReservationStatus::Confirmed->value)
        ->assertJsonPath('data.party_size', 4)
        ->assertJsonPath('data.organizer_id', $user->id)
        ->assertJsonPath('data.is_preorder', false)
        ->assertJsonCount(1, 'data.participants');

    Event::assertDispatched(ReservationCreated::class);
});

it('requires authentication', function () {
    $restaurant = Restaurant::factory()->published()->create();

    $this->postJson("/api/restaurants/{$restaurant->id}/reservations", [])
        ->assertUnauthorized();
});

it('forbids a non-client role from booking', function () {
    actingAsRestaurateur();
    $restaurant = Restaurant::factory()->published()->create();
    $date = CarbonImmutable::parse('2026-06-20');
    $service = bookableService($restaurant, $date);

    $this->postJson("/api/restaurants/{$restaurant->id}/reservations", [
        'service_id' => $service->id,
        'date' => '2026-06-20',
        'reserved_at' => '2026-06-20 12:00:00',
        'party_size' => 2,
    ])->assertForbidden();
});

it('returns 404 when the restaurant is not published', function () {
    actingAsClient();
    $restaurant = Restaurant::factory()->create(); // brouillon
    $date = CarbonImmutable::parse('2026-06-20');
    $service = bookableService($restaurant, $date);

    $this->postJson("/api/restaurants/{$restaurant->id}/reservations", [
        'service_id' => $service->id,
        'date' => '2026-06-20',
        'reserved_at' => '2026-06-20 12:00:00',
        'party_size' => 2,
    ])->assertNotFound();
});

it('validates required fields', function () {
    actingAsClient();
    $restaurant = Restaurant::factory()->published()->create();

    $this->postJson("/api/restaurants/{$restaurant->id}/reservations", [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['service_id', 'date', 'reserved_at', 'party_size']);
});

it('rejects a party size below one', function () {
    actingAsClient();
    $restaurant = Restaurant::factory()->published()->create();
    $date = CarbonImmutable::parse('2026-06-20');
    $service = bookableService($restaurant, $date);

    $this->postJson("/api/restaurants/{$restaurant->id}/reservations", [
        'service_id' => $service->id,
        'date' => '2026-06-20',
        'reserved_at' => '2026-06-20 12:00:00',
        'party_size' => 0,
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('party_size');
});

it('rejects a service belonging to another restaurant', function () {
    actingAsClient();
    $restaurant = Restaurant::factory()->published()->create();
    $otherRestaurant = Restaurant::factory()->published()->create();
    $date = CarbonImmutable::parse('2026-06-20');
    $otherService = bookableService($otherRestaurant, $date);

    $this->postJson("/api/restaurants/{$restaurant->id}/reservations", [
        'service_id' => $otherService->id,
        'date' => '2026-06-20',
        'reserved_at' => '2026-06-20 12:00:00',
        'party_size' => 2,
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('service_id');
});

it('returns 409 SLOT_UNAVAILABLE when the slot is saturated', function () {
    actingAsClient();
    $restaurant = Restaurant::factory()->published()->create();
    $date = CarbonImmutable::parse('2026-06-20');
    // Pacing à 4 couverts par créneau : on remplit 12:00 avec 4 couverts existants.
    $service = bookableService($restaurant, $date, simultaneous: 40, perSlot: 4);

    Reservation::factory()->forSlot($service, $date->setTime(12, 0), 4)->create();

    $this->postJson("/api/restaurants/{$restaurant->id}/reservations", [
        'service_id' => $service->id,
        'date' => '2026-06-20',
        'reserved_at' => '2026-06-20 12:00:00',
        'party_size' => 1,
    ])
        ->assertStatus(409)
        ->assertJsonPath('code', 'SLOT_UNAVAILABLE');
});

it('rejects a pre-order when the restaurant does not accept them', function () {
    actingAsClient();
    $restaurant = Restaurant::factory()->published()->create(['accepts_preorders' => false]);
    $date = CarbonImmutable::parse('2026-06-20');
    $service = bookableService($restaurant, $date);

    $this->postJson("/api/restaurants/{$restaurant->id}/reservations", [
        'service_id' => $service->id,
        'date' => '2026-06-20',
        'reserved_at' => '2026-06-20 12:00:00',
        'party_size' => 2,
        'is_preorder' => true,
    ])
        ->assertStatus(422)
        ->assertJsonPath('code', 'PREORDERS_NOT_ACCEPTED');
});

it('allows a pre-order when the restaurant accepts them', function () {
    actingAsClient();
    $restaurant = Restaurant::factory()->published()->create(['accepts_preorders' => true]);
    $date = CarbonImmutable::parse('2026-06-20');
    $service = bookableService($restaurant, $date);

    $this->postJson("/api/restaurants/{$restaurant->id}/reservations", [
        'service_id' => $service->id,
        'date' => '2026-06-20',
        'reserved_at' => '2026-06-20 12:00:00',
        'party_size' => 2,
        'is_preorder' => true,
    ])
        ->assertCreated()
        ->assertJsonPath('data.is_preorder', true);
});
