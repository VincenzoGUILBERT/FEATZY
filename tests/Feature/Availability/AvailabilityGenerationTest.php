<?php

use App\Models\Restaurant;
use App\Models\ScheduleException;
use App\Models\ServiceAvailability;
use App\Models\ServiceSchedule;
use Carbon\CarbonImmutable;

beforeEach(function () {
    $this->withHeader('Origin', config('app.frontend_url'));
    CarbonImmutable::setTestNow('2026-06-15 09:00:00');
});

afterEach(function () {
    CarbonImmutable::setTestNow();
});

function tomorrow(): string
{
    return CarbonImmutable::today()->addDay()->toDateString();
}

function dinnerScheduleFor(Restaurant $restaurant, int $capacity = 40, int $maxPartySize = 8): ServiceSchedule
{
    return ServiceSchedule::factory()->for($restaurant)->create([
        'day_of_week' => CarbonImmutable::parse(tomorrow())->dayOfWeek,
        'service_type' => 'dinner',
        'start_time' => '19:00:00',
        'end_time' => '22:30:00',
        'capacity' => $capacity,
        'max_party_size' => $maxPartySize,
        'is_active' => true,
    ]);
}

it('generates a bookable slot from a weekly schedule', function () {
    $owner = actingAsRestaurateur();
    $restaurant = Restaurant::factory()->for($owner, 'owner')->create(['booking_horizon_days' => 30]);
    dinnerScheduleFor($restaurant, 40, 8);

    $this->postJson("/api/restaurants/{$restaurant->id}/availabilities/generate", [
        'from' => tomorrow(),
        'to' => tomorrow(),
    ])
        ->assertOk()
        ->assertJsonPath('data.created', 1);

    $this->assertDatabaseHas('service_availabilities', [
        'restaurant_id' => $restaurant->id,
        'date' => tomorrow(),
        'service_type' => 'dinner',
        'capacity' => 40,
        'booked_seats' => 0,
        'max_party_size' => 8,
    ]);
    $this->assertDatabaseCount('service_availabilities', 1);
});

it('is idempotent and preserves booked_seats', function () {
    $owner = actingAsRestaurateur();
    $restaurant = Restaurant::factory()->for($owner, 'owner')->create(['booking_horizon_days' => 30]);
    dinnerScheduleFor($restaurant, 40, 8);

    $this->postJson("/api/restaurants/{$restaurant->id}/availabilities/generate", ['from' => tomorrow(), 'to' => tomorrow()]);

    ServiceAvailability::query()->where('restaurant_id', $restaurant->id)->update(['booked_seats' => 10]);

    $this->postJson("/api/restaurants/{$restaurant->id}/availabilities/generate", ['from' => tomorrow(), 'to' => tomorrow()])
        ->assertOk()
        ->assertJsonPath('data.created', 0)
        ->assertJsonPath('data.updated', 0);

    $this->assertDatabaseCount('service_availabilities', 1);
    $this->assertDatabaseHas('service_availabilities', [
        'restaurant_id' => $restaurant->id,
        'capacity' => 40,
        'booked_seats' => 10,
    ]);
});

it('never lowers capacity below booked_seats', function () {
    $owner = actingAsRestaurateur();
    $restaurant = Restaurant::factory()->for($owner, 'owner')->create(['booking_horizon_days' => 30]);
    dinnerScheduleFor($restaurant, 10, 8);

    ServiceAvailability::factory()->for($restaurant)->create([
        'date' => tomorrow(),
        'service_type' => 'dinner',
        'capacity' => 40,
        'booked_seats' => 30,
        'max_party_size' => 8,
    ]);

    $this->postJson("/api/restaurants/{$restaurant->id}/availabilities/generate", ['from' => tomorrow(), 'to' => tomorrow()])
        ->assertOk()
        ->assertJsonPath('data.clamped', 1);

    $this->assertDatabaseHas('service_availabilities', [
        'restaurant_id' => $restaurant->id,
        'capacity' => 30,
        'booked_seats' => 30,
    ]);
});

it('deletes an unbooked slot that is no longer open', function () {
    $owner = actingAsRestaurateur();
    $restaurant = Restaurant::factory()->for($owner, 'owner')->create(['booking_horizon_days' => 30]);

    $slot = ServiceAvailability::factory()->for($restaurant)->create([
        'date' => tomorrow(),
        'service_type' => 'dinner',
        'capacity' => 40,
        'booked_seats' => 0,
    ]);

    $this->postJson("/api/restaurants/{$restaurant->id}/availabilities/generate", ['from' => tomorrow(), 'to' => tomorrow()])
        ->assertOk()
        ->assertJsonPath('data.deleted', 1);

    $this->assertDatabaseMissing('service_availabilities', ['id' => $slot->id]);
});

it('leaves a booked slot untouched when it is no longer open', function () {
    $owner = actingAsRestaurateur();
    $restaurant = Restaurant::factory()->for($owner, 'owner')->create(['booking_horizon_days' => 30]);

    $slot = ServiceAvailability::factory()->for($restaurant)->create([
        'date' => tomorrow(),
        'service_type' => 'dinner',
        'capacity' => 40,
        'booked_seats' => 15,
    ]);

    $this->postJson("/api/restaurants/{$restaurant->id}/availabilities/generate", ['from' => tomorrow(), 'to' => tomorrow()])
        ->assertOk()
        ->assertJsonPath('data.deleted', 0);

    $this->assertDatabaseHas('service_availabilities', [
        'id' => $slot->id,
        'capacity' => 40,
        'booked_seats' => 15,
    ]);
});

it('updates a slot when only the max party size changes', function () {
    $owner = actingAsRestaurateur();
    $restaurant = Restaurant::factory()->for($owner, 'owner')->create(['booking_horizon_days' => 30]);
    $schedule = dinnerScheduleFor($restaurant, 40, 8);

    $this->postJson("/api/restaurants/{$restaurant->id}/availabilities/generate", ['from' => tomorrow(), 'to' => tomorrow()]);

    $schedule->update(['max_party_size' => 10]);

    $this->postJson("/api/restaurants/{$restaurant->id}/availabilities/generate", ['from' => tomorrow(), 'to' => tomorrow()])
        ->assertOk()
        ->assertJsonPath('data.updated', 1);

    $this->assertDatabaseHas('service_availabilities', [
        'restaurant_id' => $restaurant->id,
        'capacity' => 40,
        'max_party_size' => 10,
    ]);
});

it('generates lunch and dinner across a multi-day range', function () {
    $owner = actingAsRestaurateur();
    $restaurant = Restaurant::factory()->for($owner, 'owner')->create(['booking_horizon_days' => 30]);

    foreach (range(0, 6) as $dayOfWeek) {
        ServiceSchedule::factory()->for($restaurant)->create([
            'day_of_week' => $dayOfWeek, 'service_type' => 'lunch',
            'start_time' => '12:00:00', 'end_time' => '14:30:00',
            'capacity' => 30, 'max_party_size' => 6, 'is_active' => true,
        ]);
        ServiceSchedule::factory()->for($restaurant)->create([
            'day_of_week' => $dayOfWeek, 'service_type' => 'dinner',
            'start_time' => '19:00:00', 'end_time' => '22:30:00',
            'capacity' => 40, 'max_party_size' => 8, 'is_active' => true,
        ]);
    }

    $from = CarbonImmutable::today()->addDay();
    $to = $from->addDays(6);

    $this->postJson("/api/restaurants/{$restaurant->id}/availabilities/generate", [
        'from' => $from->toDateString(),
        'to' => $to->toDateString(),
    ])
        ->assertOk()
        ->assertJsonPath('data.created', 14);

    $this->assertDatabaseCount('service_availabilities', 14);
});

it('does not materialise a slot closed by an exception', function () {
    $owner = actingAsRestaurateur();
    $restaurant = Restaurant::factory()->for($owner, 'owner')->create(['booking_horizon_days' => 30]);
    dinnerScheduleFor($restaurant, 40, 8);
    ScheduleException::factory()->for($restaurant)->closed()->create([
        'date' => tomorrow(),
        'service_type' => 'dinner',
    ]);

    $this->postJson("/api/restaurants/{$restaurant->id}/availabilities/generate", ['from' => tomorrow(), 'to' => tomorrow()])
        ->assertOk()
        ->assertJsonPath('data.created', 0);

    $this->assertDatabaseCount('service_availabilities', 0);
});

it('rejects a from date in the past', function () {
    $owner = actingAsRestaurateur();
    $restaurant = Restaurant::factory()->for($owner, 'owner')->create();

    $this->postJson("/api/restaurants/{$restaurant->id}/availabilities/generate", [
        'from' => CarbonImmutable::today()->subDay()->toDateString(),
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('from');
});

it('rejects a to date beyond the booking horizon', function () {
    $owner = actingAsRestaurateur();
    $restaurant = Restaurant::factory()->for($owner, 'owner')->create(['booking_horizon_days' => 30]);

    $this->postJson("/api/restaurants/{$restaurant->id}/availabilities/generate", [
        'to' => CarbonImmutable::today()->addDays(40)->toDateString(),
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('to');
});

it('rejects a to date before the from date', function () {
    $owner = actingAsRestaurateur();
    $restaurant = Restaurant::factory()->for($owner, 'owner')->create(['booking_horizon_days' => 30]);

    $this->postJson("/api/restaurants/{$restaurant->id}/availabilities/generate", [
        'from' => CarbonImmutable::today()->addDays(5)->toDateString(),
        'to' => CarbonImmutable::today()->addDays(2)->toDateString(),
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('to');
});

it('forbids generating for a restaurant of another owner', function () {
    actingAsRestaurateur();
    $foreign = Restaurant::factory()->create();

    $this->postJson("/api/restaurants/{$foreign->id}/availabilities/generate", ['from' => tomorrow(), 'to' => tomorrow()])
        ->assertForbidden();
});

it('requires authentication to generate', function () {
    $restaurant = Restaurant::factory()->create();

    $this->postJson("/api/restaurants/{$restaurant->id}/availabilities/generate", ['from' => tomorrow(), 'to' => tomorrow()])
        ->assertUnauthorized();
});
