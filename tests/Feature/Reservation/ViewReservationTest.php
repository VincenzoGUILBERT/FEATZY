<?php

use App\Enums\ParticipantRole;
use App\Models\Reservation;
use App\Models\ReservationParticipant;
use App\Models\Restaurant;

beforeEach(function () {
    $this->withHeader('Origin', config('app.frontend_url'));
});

it('lets the organizer view their reservation', function () {
    $user = actingAsClient();
    $reservation = Reservation::factory()->for($user, 'organizer')->create();

    $this->getJson("/api/reservations/{$reservation->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $reservation->id);
});

it('lets a participant view the reservation', function () {
    $user = actingAsClient();
    $reservation = Reservation::factory()->create();
    ReservationParticipant::factory()->for($reservation)->for($user)->create([
        'role' => ParticipantRole::Guest,
    ]);

    $this->getJson("/api/reservations/{$reservation->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $reservation->id);
});

it('lets the restaurant owner view the reservation', function () {
    $owner = actingAsRestaurateur();
    $restaurant = Restaurant::factory()->for($owner, 'owner')->create();
    $reservation = Reservation::factory()->for($restaurant)->create();

    $this->getJson("/api/reservations/{$reservation->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $reservation->id);
});

it('forbids an unrelated user from viewing the reservation', function () {
    actingAsClient();
    $reservation = Reservation::factory()->create();

    $this->getJson("/api/reservations/{$reservation->id}")
        ->assertForbidden();
});

it('requires authentication to view a reservation', function () {
    $reservation = Reservation::factory()->create();

    $this->getJson("/api/reservations/{$reservation->id}")
        ->assertUnauthorized();
});

it('lists only the reservations organized by the user', function () {
    $user = actingAsClient();
    Reservation::factory()->count(2)->for($user, 'organizer')->create();
    Reservation::factory()->count(3)->create();

    $this->getJson('/api/me/reservations')
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

it('returns pagination metadata for the reservation list', function () {
    $user = actingAsClient();
    Reservation::factory()->count(20)->for($user, 'organizer')->create();

    $this->getJson('/api/me/reservations')
        ->assertOk()
        ->assertJsonPath('meta.total', 20)
        ->assertJsonPath('meta.current_page', 1);
});

it('requires authentication to list reservations', function () {
    $this->getJson('/api/me/reservations')->assertUnauthorized();
});
