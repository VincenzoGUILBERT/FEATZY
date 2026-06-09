<?php

use App\Actions\Reservation\CancelReservationAction;
use App\Enums\ReservationStatus;
use App\Events\Reservation\ReservationCancelled;
use App\Exceptions\InvalidStatusTransitionException;
use App\Models\Reservation;
use App\Models\Restaurant;
use App\Models\Service;
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
 * Crée un restaurant publié (délai d'annulation 24 h) + un service ouvert le jour
 * du créneau + une réservation confirmée future, avec reserved_at/slot_at/ends_at
 * cohérents. Les surcharges s'appliquent à la réservation (ex. reserved_at proche,
 * status non confirmé).
 *
 * @param  array<string, mixed>  $overrides
 */
function confirmedReservation(User $organizer, array $overrides = []): Reservation
{
    $restaurant = Restaurant::factory()->published()->create([
        'cancellation_deadline_hours' => 24,
    ]);

    $service = Service::factory()->for($restaurant)->create([
        'max_simultaneous_covers' => 40,
        'max_covers_per_slot' => 8,
    ]);

    // Créneau par défaut : +5 jours à midi (largement après le délai d'annulation).
    $reservedAt = CarbonImmutable::parse('2026-06-20 12:00:00');

    $service->schedules()->create([
        'day_of_week' => $reservedAt->dayOfWeek,
        'opens_at' => '12:00:00',
        'last_seating_at' => '13:30:00',
        'closes_at' => '15:00:00',
        'crosses_midnight' => false,
    ]);

    $duration = $service->effectiveSeatingDuration();

    return Reservation::factory()
        ->for($restaurant)
        ->for($organizer, 'organizer')
        ->create(array_merge([
            'service_id' => $service->id,
            'capacity_pool_key' => $service->capacity_pool_key,
            'party_size' => 4,
            'reserved_at' => $reservedAt,
            'slot_at' => $reservedAt,
            'ends_at' => $reservedAt->addMinutes($duration),
            'seating_duration_minutes' => $duration,
            'status' => ReservationStatus::Confirmed,
        ], $overrides));
}

it('cancels a reservation within the deadline', function () {
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

it('rejects cancellation once the deadline has passed for the organizer', function () {
    $user = actingAsClient();

    // reserved_at à +1 h alors que le délai est de 24 h : le deadline est déjà dépassé.
    $reservedAt = CarbonImmutable::now()->addHour();
    $reservation = confirmedReservation($user, [
        'reserved_at' => $reservedAt,
        'slot_at' => $reservedAt,
        'ends_at' => $reservedAt->addMinutes(90),
    ]);

    $this->postJson("/api/reservations/{$reservation->id}/cancel")
        ->assertStatus(422)
        ->assertJsonPath('code', 'CANCELLATION_DEADLINE_PASSED');

    $this->assertDatabaseHas('reservations', [
        'id' => $reservation->id,
        'status' => ReservationStatus::Confirmed->value,
    ]);
});

it('rejects cancelling a reservation that is not confirmed', function () {
    $user = actingAsClient();
    $reservation = confirmedReservation($user, [
        'status' => ReservationStatus::Cancelled,
    ]);

    $this->postJson("/api/reservations/{$reservation->id}/cancel")
        ->assertStatus(422)
        ->assertJsonPath('code', 'INVALID_STATUS_TRANSITION');
});

it('lets the restaurant owner cancel even after the deadline', function () {
    $owner = actingAsRestaurateur();
    $client = User::factory()->client()->create();

    $restaurant = Restaurant::factory()->published()->for($owner, 'owner')->create([
        'cancellation_deadline_hours' => 24,
    ]);

    $service = Service::factory()->for($restaurant)->create([
        'max_simultaneous_covers' => 40,
        'max_covers_per_slot' => 8,
    ]);

    // reserved_at à +1 h : un client serait hors délai, le propriétaire l'outrepasse.
    $reservedAt = CarbonImmutable::now()->addHour();
    $reservation = Reservation::factory()
        ->for($restaurant)
        ->for($client, 'organizer')
        ->create([
            'service_id' => $service->id,
            'capacity_pool_key' => $service->capacity_pool_key,
            'party_size' => 4,
            'reserved_at' => $reservedAt,
            'slot_at' => $reservedAt,
            'ends_at' => $reservedAt->addMinutes(90),
            'seating_duration_minutes' => 90,
            'status' => ReservationStatus::Confirmed,
        ]);

    $this->postJson("/api/reservations/{$reservation->id}/cancel")
        ->assertOk()
        ->assertJsonPath('data.status', ReservationStatus::Cancelled->value)
        ->assertJsonPath('data.cancelled_by_id', $owner->id);

    $this->assertDatabaseHas('reservations', [
        'id' => $reservation->id,
        'status' => ReservationStatus::Cancelled->value,
    ]);
});

it('requires authentication', function () {
    $organizer = User::factory()->client()->create();
    $reservation = confirmedReservation($organizer);

    $this->postJson("/api/reservations/{$reservation->id}/cancel")
        ->assertUnauthorized();
});

it('raises an invalid-transition error when a stale cancel is replayed', function () {
    $user = actingAsClient();
    $reservation = confirmedReservation($user);

    $action = app(CancelReservationAction::class);

    // Deux instances chargées avant tout cancel : toutes deux voient « confirmed »,
    // simulant deux requêtes concurrentes passant le garde en mémoire.
    $first = Reservation::query()->findOrFail($reservation->id);
    $second = Reservation::query()->findOrFail($reservation->id);

    $action->handle($first, $user);

    expect(fn () => $action->handle($second, $user))
        ->toThrow(InvalidStatusTransitionException::class);

    $this->assertDatabaseHas('reservations', [
        'id' => $reservation->id,
        'status' => ReservationStatus::Cancelled->value,
    ]);
});
