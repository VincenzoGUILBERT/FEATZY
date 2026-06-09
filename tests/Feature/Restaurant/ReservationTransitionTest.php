<?php

use App\Enums\ReservationStatus;
use App\Models\Reservation;
use App\Models\Restaurant;
use Carbon\CarbonImmutable;

beforeEach(function () {
    $this->withHeader('Origin', config('app.frontend_url'));
    CarbonImmutable::setTestNow('2026-06-15 09:00:00');
});

afterEach(function () {
    CarbonImmutable::setTestNow();
});

it('seats a confirmed reservation', function () {
    $owner = actingAsRestaurateur();
    $restaurant = Restaurant::factory()->for($owner, 'owner')->create();
    $reservation = Reservation::factory()->for($restaurant)->create(['status' => ReservationStatus::Confirmed]);

    $this->postJson("/api/reservations/{$reservation->id}/seat")
        ->assertOk()
        ->assertJsonPath('data.status', ReservationStatus::Seated->value)
        ->assertJsonPath('data.seated_at', fn ($value) => $value !== null);
});

it('completes a seated reservation', function () {
    $owner = actingAsRestaurateur();
    $restaurant = Restaurant::factory()->for($owner, 'owner')->create();
    $reservation = Reservation::factory()->for($restaurant)->create(['status' => ReservationStatus::Seated]);

    $this->postJson("/api/reservations/{$reservation->id}/complete")
        ->assertOk()
        ->assertJsonPath('data.status', ReservationStatus::Completed->value)
        ->assertJsonPath('data.completed_at', fn ($value) => $value !== null);
});

it('marks a confirmed reservation as no-show', function () {
    $owner = actingAsRestaurateur();
    $restaurant = Restaurant::factory()->for($owner, 'owner')->create();
    $reservation = Reservation::factory()->for($restaurant)->create(['status' => ReservationStatus::Confirmed]);

    $this->postJson("/api/reservations/{$reservation->id}/no-show")
        ->assertOk()
        ->assertJsonPath('data.status', ReservationStatus::NoShow->value);
});

it('marks a seated reservation as no-show', function () {
    $owner = actingAsRestaurateur();
    $restaurant = Restaurant::factory()->for($owner, 'owner')->create();
    $reservation = Reservation::factory()->for($restaurant)->create(['status' => ReservationStatus::Seated]);

    $this->postJson("/api/reservations/{$reservation->id}/no-show")
        ->assertOk()
        ->assertJsonPath('data.status', ReservationStatus::NoShow->value);
});

it('rejects an illegal reservation transition', function () {
    $owner = actingAsRestaurateur();
    $restaurant = Restaurant::factory()->for($owner, 'owner')->create();
    $reservation = Reservation::factory()->for($restaurant)->create(['status' => ReservationStatus::Confirmed]);

    // complete exige une réservation installée, pas seulement confirmée.
    $this->postJson("/api/reservations/{$reservation->id}/complete")
        ->assertStatus(422)
        ->assertJsonPath('code', 'INVALID_STATUS_TRANSITION');

    $this->assertDatabaseHas('reservations', ['id' => $reservation->id, 'status' => ReservationStatus::Confirmed->value]);
});

it('forbids a non-owner from managing a reservation', function () {
    actingAsRestaurateur();
    $reservation = Reservation::factory()->create(); // appartient à un autre restaurant

    $this->postJson("/api/reservations/{$reservation->id}/seat")->assertForbidden();
});

it('requires authentication to manage a reservation', function () {
    $reservation = Reservation::factory()->create();

    $this->postJson("/api/reservations/{$reservation->id}/seat")->assertUnauthorized();
});

it('lets the restaurant owner cancel past the deadline', function () {
    $owner = actingAsRestaurateur();
    $restaurant = Restaurant::factory()->for($owner, 'owner')->create(['cancellation_deadline_hours' => 24]);
    // Arrivée à 12:00 aujourd'hui : deadline = hier 12:00 → déjà passée pour l'organisateur,
    // mais le propriétaire l'outrepasse.
    $reservedAt = CarbonImmutable::parse('2026-06-15 12:00:00');
    $reservation = Reservation::factory()->for($restaurant)->create([
        'status' => ReservationStatus::Confirmed,
        'reserved_at' => $reservedAt,
        'slot_at' => $reservedAt,
        'ends_at' => $reservedAt->addMinutes(90),
        'party_size' => 4,
    ]);

    $this->postJson("/api/reservations/{$reservation->id}/cancel")
        ->assertOk()
        ->assertJsonPath('data.status', ReservationStatus::Cancelled->value)
        ->assertJsonPath('data.cancelled_by_id', $owner->id);
});
