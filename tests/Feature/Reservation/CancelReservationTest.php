<?php

use App\Actions\Reservation\CancelReservationAction;
use App\Enums\ReservationStatus;
use App\Enums\ServiceType;
use App\Events\Reservation\ReservationCancelled;
use App\Exceptions\InvalidStatusTransitionException;
use App\Models\Reservation;
use App\Models\Restaurant;
use App\Models\ServiceAvailability;
use App\Models\User;
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
 * Build a confirmed reservation owned by $organizer on a slot we control.
 *
 * @param  array<string, mixed>  $slotAttributes
 * @param  array<string, mixed>  $reservationAttributes
 */
function confirmedReservation(User $organizer, array $slotAttributes = [], array $reservationAttributes = []): Reservation
{
    $restaurant = Restaurant::factory()->published()->create([
        'cancellation_deadline_hours' => 24,
    ]);

    $slot = ServiceAvailability::factory()->for($restaurant)->create(array_merge([
        'date' => CarbonImmutable::today()->addDays(5)->toDateString(),
        'service_type' => ServiceType::Dinner,
        'capacity' => 40,
        'booked_seats' => 4,
        'max_party_size' => 8,
    ], $slotAttributes));

    return Reservation::factory()->for($restaurant)->for($organizer, 'organizer')->create(array_merge([
        'service_availability_id' => $slot->id,
        'reservation_date' => $slot->date->toDateString(),
        'service_type' => $slot->service_type,
        'party_size' => 4,
        'status' => ReservationStatus::Confirmed,
    ], $reservationAttributes));
}

it('cancels a reservation within the deadline and restores capacity', function () {
    Event::fake([ReservationCancelled::class]);

    $user = actingAsClient();
    $reservation = confirmedReservation($user);

    $this->postJson("/api/reservations/{$reservation->id}/cancel", [
        'cancellation_reason' => 'Plans changed',
    ])
        ->assertOk()
        ->assertJsonPath('data.status', ReservationStatus::Cancelled->value)
        ->assertJsonPath('data.cancellation_reason', 'Plans changed')
        ->assertJsonPath('data.cancelled_by_id', $user->id)
        ->assertJsonPath('data.cancelled_at', fn ($value) => $value !== null);

    $this->assertDatabaseHas('reservations', [
        'id' => $reservation->id,
        'status' => ReservationStatus::Cancelled->value,
        'cancelled_by_id' => $user->id,
    ]);

    $this->assertDatabaseHas('service_availabilities', [
        'id' => $reservation->service_availability_id,
        'booked_seats' => 0,
    ]);

    Event::assertDispatched(ReservationCancelled::class);
});

it('forbids a non-organizer from cancelling', function () {
    $organizer = User::factory()->client()->create();
    $reservation = confirmedReservation($organizer);

    actingAsClient();

    $this->postJson("/api/reservations/{$reservation->id}/cancel")
        ->assertForbidden();

    $this->assertDatabaseHas('reservations', [
        'id' => $reservation->id,
        'status' => ReservationStatus::Confirmed->value,
    ]);
});

it('rejects cancellation once the deadline has passed', function () {
    $user = actingAsClient();
    $reservation = confirmedReservation($user, [
        'date' => CarbonImmutable::today()->toDateString(),
    ], [
        'reservation_date' => CarbonImmutable::today()->toDateString(),
    ]);

    $this->postJson("/api/reservations/{$reservation->id}/cancel")
        ->assertStatus(422)
        ->assertJsonPath('code', 'CANCELLATION_DEADLINE_PASSED');

    $this->assertDatabaseHas('reservations', [
        'id' => $reservation->id,
        'status' => ReservationStatus::Confirmed->value,
    ]);

    $this->assertDatabaseHas('service_availabilities', [
        'id' => $reservation->service_availability_id,
        'booked_seats' => 4,
    ]);
});

it('rejects cancelling a reservation that is not confirmed', function () {
    $user = actingAsClient();
    $reservation = confirmedReservation($user, [], [
        'status' => ReservationStatus::Cancelled,
    ]);

    $this->postJson("/api/reservations/{$reservation->id}/cancel")
        ->assertStatus(422)
        ->assertJsonPath('code', 'INVALID_STATUS_TRANSITION');

    $this->assertDatabaseHas('service_availabilities', [
        'id' => $reservation->service_availability_id,
        'booked_seats' => 4,
    ]);
});

it('restores only the party size, preserving other bookings', function () {
    $user = actingAsClient();
    $reservation = confirmedReservation($user, [
        'booked_seats' => 10,
    ], [
        'party_size' => 4,
    ]);

    $this->postJson("/api/reservations/{$reservation->id}/cancel")->assertOk();

    $this->assertDatabaseHas('service_availabilities', [
        'id' => $reservation->service_availability_id,
        'booked_seats' => 6,
    ]);
});

it('allows cancellation exactly at the deadline', function () {
    $user = actingAsClient();
    // Deadline = reservation day 19:00 (dinner) - 24h = previous day 19:00.
    $reservation = confirmedReservation($user, [
        'date' => CarbonImmutable::today()->addDay()->toDateString(),
        'service_type' => ServiceType::Dinner,
    ], [
        'reservation_date' => CarbonImmutable::today()->addDay()->toDateString(),
        'service_type' => ServiceType::Dinner,
    ]);

    CarbonImmutable::setTestNow(CarbonImmutable::today()->setTime(19, 0));

    $this->postJson("/api/reservations/{$reservation->id}/cancel")
        ->assertOk()
        ->assertJsonPath('data.status', ReservationStatus::Cancelled->value);
});

it('clamps restored seats at zero', function () {
    $user = actingAsClient();
    $reservation = confirmedReservation($user, [
        'booked_seats' => 2,
    ], [
        'party_size' => 5,
    ]);

    $this->postJson("/api/reservations/{$reservation->id}/cancel")
        ->assertOk();

    $this->assertDatabaseHas('service_availabilities', [
        'id' => $reservation->service_availability_id,
        'booked_seats' => 0,
    ]);
});

it('requires authentication', function () {
    $organizer = User::factory()->client()->create();
    $reservation = confirmedReservation($organizer);

    $this->postJson("/api/reservations/{$reservation->id}/cancel")
        ->assertUnauthorized();
});

it('restores seats exactly once when a stale cancel is replayed', function () {
    $user = actingAsClient();
    $reservation = confirmedReservation($user); // booked_seats = 4, party_size = 4

    $action = app(CancelReservationAction::class);

    // Two instances loaded before either cancel: both still see "confirmed",
    // mimicking two concurrent requests that pass the in-memory guard.
    $first = Reservation::query()->findOrFail($reservation->id);
    $second = Reservation::query()->findOrFail($reservation->id);

    $action->handle($first, $user);

    expect(fn () => $action->handle($second, $user))
        ->toThrow(InvalidStatusTransitionException::class);

    $this->assertDatabaseHas('service_availabilities', [
        'id' => $reservation->service_availability_id,
        'booked_seats' => 0,
    ]);
});
