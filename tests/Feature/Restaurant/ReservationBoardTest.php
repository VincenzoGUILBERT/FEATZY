<?php

use App\Enums\ReservationStatus;
use App\Models\Reservation;
use App\Models\Restaurant;
use Carbon\CarbonImmutable;

beforeEach(function () {
    $this->withHeader('Origin', config('app.frontend_url'));
});

it('lists the restaurant reservations for the owner', function () {
    $owner = actingAsRestaurateur();
    $restaurant = Restaurant::factory()->for($owner, 'owner')->create();
    Reservation::factory()->count(3)->for($restaurant)->create();
    Reservation::factory()->count(2)->create(); // d'autres restaurants

    $this->getJson("/api/restaurants/{$restaurant->id}/reservations")
        ->assertOk()
        ->assertJsonCount(3, 'data');
});

it('filters the board by status', function () {
    $owner = actingAsRestaurateur();
    $restaurant = Restaurant::factory()->for($owner, 'owner')->create();
    Reservation::factory()->count(2)->for($restaurant)->create(['status' => ReservationStatus::Confirmed]);
    Reservation::factory()->for($restaurant)->create(['status' => ReservationStatus::Cancelled]);

    $this->getJson("/api/restaurants/{$restaurant->id}/reservations?filter[status]=confirmed")
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

it('filters the board by reservation date', function () {
    $owner = actingAsRestaurateur();
    $restaurant = Restaurant::factory()->for($owner, 'owner')->create();

    // Le filtre date applique whereDate sur reserved_at : on pose reserved_at/slot_at/ends_at cohérents.
    $target = CarbonImmutable::parse('2026-06-20 12:00:00');
    $other = CarbonImmutable::parse('2026-06-25 12:00:00');

    foreach ([$target, $target] as $reservedAt) {
        Reservation::factory()->for($restaurant)->create([
            'reserved_at' => $reservedAt,
            'slot_at' => $reservedAt,
            'ends_at' => $reservedAt->addMinutes(90),
        ]);
    }

    Reservation::factory()->for($restaurant)->create([
        'reserved_at' => $other,
        'slot_at' => $other,
        'ends_at' => $other->addMinutes(90),
    ]);

    $this->getJson("/api/restaurants/{$restaurant->id}/reservations?filter[date]={$target->toDateString()}")
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

it('forbids a non-owner from viewing the board', function () {
    actingAsRestaurateur();
    $restaurant = Restaurant::factory()->create(); // un autre propriétaire

    $this->getJson("/api/restaurants/{$restaurant->id}/reservations")->assertForbidden();
});

it('requires authentication to view the reservation board', function () {
    $restaurant = Restaurant::factory()->create();

    $this->getJson("/api/restaurants/{$restaurant->id}/reservations")->assertUnauthorized();
});
