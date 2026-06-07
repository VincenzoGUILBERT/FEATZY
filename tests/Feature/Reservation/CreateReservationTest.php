<?php

use App\Enums\ParticipantRole;
use App\Enums\ReservationStatus;
use App\Enums\ServiceType;
use App\Events\Reservation\ReservationCreated;
use App\Models\Restaurant;
use App\Models\ServiceAvailability;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    $this->withHeader('Origin', config('app.frontend_url'));
    CarbonImmutable::setTestNow('2026-06-15 09:00:00');
});

afterEach(function () {
    CarbonImmutable::setTestNow();
});

it('books a slot, decrements capacity and creates the organizer participant', function () {
    Event::fake([ReservationCreated::class]);

    $user = actingAsClient();
    $restaurant = Restaurant::factory()->published()->create();
    $slot = ServiceAvailability::factory()->for($restaurant)->create([
        'date' => CarbonImmutable::today()->addDays(5)->toDateString(),
        'service_type' => ServiceType::Dinner,
        'capacity' => 40,
        'booked_seats' => 0,
        'max_party_size' => 8,
    ]);

    $this->postJson("/api/restaurants/{$restaurant->id}/reservations", [
        'service_availability_id' => $slot->id,
        'party_size' => 4,
        'special_requests' => 'Window table please',
    ])
        ->assertCreated()
        ->assertJsonPath('data.status', ReservationStatus::Confirmed->value)
        ->assertJsonPath('data.party_size', 4)
        ->assertJsonPath('data.organizer_id', $user->id)
        ->assertJsonPath('data.reservation_date', $slot->date->toDateString())
        ->assertJsonPath('data.service_type', ServiceType::Dinner->value)
        ->assertJsonPath('data.is_preorder', false)
        ->assertJsonCount(1, 'data.participants');

    $this->assertDatabaseHas('service_availabilities', [
        'id' => $slot->id,
        'booked_seats' => 4,
    ]);

    $this->assertDatabaseHas('reservation_participants', [
        'user_id' => $user->id,
        'role' => ParticipantRole::Organizer->value,
        'invitation_status' => 'accepted',
        'is_attending' => true,
    ]);

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
    $slot = ServiceAvailability::factory()->for($restaurant)->create([
        'date' => CarbonImmutable::today()->addDays(5)->toDateString(),
        'booked_seats' => 0,
    ]);

    $this->postJson("/api/restaurants/{$restaurant->id}/reservations", [
        'service_availability_id' => $slot->id,
        'party_size' => 2,
    ])->assertForbidden();
});

it('returns 404 when the restaurant is not published', function () {
    actingAsClient();
    $restaurant = Restaurant::factory()->create(); // draft
    $slot = ServiceAvailability::factory()->for($restaurant)->create([
        'date' => CarbonImmutable::today()->addDays(5)->toDateString(),
        'booked_seats' => 0,
        'max_party_size' => 8,
    ]);

    $this->postJson("/api/restaurants/{$restaurant->id}/reservations", [
        'service_availability_id' => $slot->id,
        'party_size' => 2,
    ])->assertNotFound();
});

it('validates required fields', function () {
    actingAsClient();
    $restaurant = Restaurant::factory()->published()->create();

    $this->postJson("/api/restaurants/{$restaurant->id}/reservations", [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['service_availability_id', 'party_size']);
});

it('rejects a party size below one', function () {
    actingAsClient();
    $restaurant = Restaurant::factory()->published()->create();
    $slot = ServiceAvailability::factory()->for($restaurant)->create([
        'date' => CarbonImmutable::today()->addDays(5)->toDateString(),
        'capacity' => 40,
        'booked_seats' => 0,
        'max_party_size' => 8,
    ]);

    $this->postJson("/api/restaurants/{$restaurant->id}/reservations", [
        'service_availability_id' => $slot->id,
        'party_size' => 0,
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('party_size');
});

it('rejects a slot belonging to another restaurant', function () {
    actingAsClient();
    $restaurant = Restaurant::factory()->published()->create();
    $otherSlot = ServiceAvailability::factory()->create([
        'date' => CarbonImmutable::today()->addDays(5)->toDateString(),
    ]);

    $this->postJson("/api/restaurants/{$restaurant->id}/reservations", [
        'service_availability_id' => $otherSlot->id,
        'party_size' => 2,
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('service_availability_id');
});

it('rejects a party size beyond the slot maximum', function () {
    actingAsClient();
    $restaurant = Restaurant::factory()->published()->create();
    $slot = ServiceAvailability::factory()->for($restaurant)->create([
        'date' => CarbonImmutable::today()->addDays(5)->toDateString(),
        'capacity' => 40,
        'booked_seats' => 0,
        'max_party_size' => 6,
    ]);

    $this->postJson("/api/restaurants/{$restaurant->id}/reservations", [
        'service_availability_id' => $slot->id,
        'party_size' => 8,
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('party_size');
});

it('rejects a slot in the past', function () {
    actingAsClient();
    $restaurant = Restaurant::factory()->published()->create();
    $slot = ServiceAvailability::factory()->for($restaurant)->create([
        'date' => CarbonImmutable::today()->subDay()->toDateString(),
        'capacity' => 40,
        'booked_seats' => 0,
        'max_party_size' => 8,
    ]);

    $this->postJson("/api/restaurants/{$restaurant->id}/reservations", [
        'service_availability_id' => $slot->id,
        'party_size' => 2,
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('service_availability_id');
});

it('returns 409 when the requested party exceeds remaining capacity', function () {
    actingAsClient();
    $restaurant = Restaurant::factory()->published()->create();
    $slot = ServiceAvailability::factory()->for($restaurant)->create([
        'date' => CarbonImmutable::today()->addDays(5)->toDateString(),
        'capacity' => 10,
        'booked_seats' => 8,
        'max_party_size' => null,
    ]);

    $this->postJson("/api/restaurants/{$restaurant->id}/reservations", [
        'service_availability_id' => $slot->id,
        'party_size' => 3,
    ])
        ->assertStatus(409)
        ->assertJsonPath('code', 'CAPACITY_EXCEEDED');

    $this->assertDatabaseHas('service_availabilities', [
        'id' => $slot->id,
        'booked_seats' => 8,
    ]);
});

it('rejects a pre-order when the restaurant does not accept them', function () {
    actingAsClient();
    $restaurant = Restaurant::factory()->published()->create(['accepts_preorders' => false]);
    $slot = ServiceAvailability::factory()->for($restaurant)->create([
        'date' => CarbonImmutable::today()->addDays(5)->toDateString(),
        'capacity' => 40,
        'booked_seats' => 0,
        'max_party_size' => 8,
    ]);

    $this->postJson("/api/restaurants/{$restaurant->id}/reservations", [
        'service_availability_id' => $slot->id,
        'party_size' => 2,
        'is_preorder' => true,
    ])
        ->assertStatus(422)
        ->assertJsonPath('code', 'PREORDERS_NOT_ACCEPTED');

    $this->assertDatabaseHas('service_availabilities', [
        'id' => $slot->id,
        'booked_seats' => 0,
    ]);
});

it('allows a pre-order when the restaurant accepts them', function () {
    actingAsClient();
    $restaurant = Restaurant::factory()->published()->create(['accepts_preorders' => true]);
    $slot = ServiceAvailability::factory()->for($restaurant)->create([
        'date' => CarbonImmutable::today()->addDays(5)->toDateString(),
        'capacity' => 40,
        'booked_seats' => 0,
        'max_party_size' => 8,
    ]);

    $this->postJson("/api/restaurants/{$restaurant->id}/reservations", [
        'service_availability_id' => $slot->id,
        'party_size' => 2,
        'is_preorder' => true,
    ])
        ->assertCreated()
        ->assertJsonPath('data.is_preorder', true);
});

it('enforces capacity across successive bookings without overbooking', function () {
    // The anti-overbooking guarantee is enforced atomically at the SQL level
    // (conditional UPDATE + DB CHECK); this drives it through successive bookings
    // to assert booked_seats tracks capacity exactly and never exceeds it.
    actingAsClient();
    $restaurant = Restaurant::factory()->published()->create();
    $slot = ServiceAvailability::factory()->for($restaurant)->create([
        'date' => CarbonImmutable::today()->addDays(5)->toDateString(),
        'capacity' => 4,
        'booked_seats' => 0,
        'max_party_size' => null,
    ]);

    $book = fn (int $party) => $this->postJson("/api/restaurants/{$restaurant->id}/reservations", [
        'service_availability_id' => $slot->id,
        'party_size' => $party,
    ]);

    $book(3)->assertCreated();
    $book(2)->assertStatus(409); // 3 + 2 > 4
    $book(1)->assertCreated();   // 3 + 1 = 4
    $book(1)->assertStatus(409); // full

    $slot->refresh();
    expect($slot->booked_seats)->toBe(4)
        ->and($slot->booked_seats)->toBeLessThanOrEqual($slot->capacity);
});
