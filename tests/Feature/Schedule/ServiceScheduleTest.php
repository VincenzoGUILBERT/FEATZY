<?php

use App\Models\Restaurant;
use App\Models\ServiceSchedule;

beforeEach(function () {
    $this->withHeader('Origin', config('app.frontend_url'));
});

it('creates a service schedule', function () {
    $owner = actingAsRestaurateur();
    $restaurant = Restaurant::factory()->for($owner, 'owner')->create();

    $this->postJson("/api/restaurants/{$restaurant->id}/service-schedules", [
        'day_of_week' => 1,
        'service_type' => 'dinner',
        'start_time' => '19:00:00',
        'end_time' => '22:30:00',
        'capacity' => 40,
        'max_party_size' => 8,
    ])
        ->assertCreated()
        ->assertJsonPath('data.day_of_week', 1)
        ->assertJsonPath('data.service_type', 'dinner')
        ->assertJsonPath('data.capacity', 40)
        ->assertJsonPath('data.is_active', true);
});

it('lists schedules ordered by day', function () {
    $owner = actingAsRestaurateur();
    $restaurant = Restaurant::factory()->for($owner, 'owner')->create();
    ServiceSchedule::factory()->for($restaurant)->create(['day_of_week' => 5, 'service_type' => 'dinner']);
    ServiceSchedule::factory()->for($restaurant)->create(['day_of_week' => 1, 'service_type' => 'lunch']);

    $this->getJson("/api/restaurants/{$restaurant->id}/service-schedules")
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.day_of_week', 1);
});

it('rejects a duplicate day and service combination', function () {
    $owner = actingAsRestaurateur();
    $restaurant = Restaurant::factory()->for($owner, 'owner')->create();
    ServiceSchedule::factory()->for($restaurant)->create(['day_of_week' => 1, 'service_type' => 'dinner']);

    $this->postJson("/api/restaurants/{$restaurant->id}/service-schedules", [
        'day_of_week' => 1,
        'service_type' => 'dinner',
        'start_time' => '19:00:00',
        'end_time' => '22:30:00',
        'capacity' => 40,
        'max_party_size' => 8,
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('service_type');
});

it('rejects an end time before the start time', function () {
    $owner = actingAsRestaurateur();
    $restaurant = Restaurant::factory()->for($owner, 'owner')->create();

    $this->postJson("/api/restaurants/{$restaurant->id}/service-schedules", [
        'day_of_week' => 1,
        'service_type' => 'lunch',
        'start_time' => '22:00:00',
        'end_time' => '19:00:00',
        'capacity' => 40,
        'max_party_size' => 8,
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('end_time');
});

it('rejects a max party size greater than the capacity', function () {
    $owner = actingAsRestaurateur();
    $restaurant = Restaurant::factory()->for($owner, 'owner')->create();

    $this->postJson("/api/restaurants/{$restaurant->id}/service-schedules", [
        'day_of_week' => 1,
        'service_type' => 'lunch',
        'start_time' => '12:00:00',
        'end_time' => '14:30:00',
        'capacity' => 10,
        'max_party_size' => 20,
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('max_party_size');
});

it('rejects an invalid day of week', function () {
    $owner = actingAsRestaurateur();
    $restaurant = Restaurant::factory()->for($owner, 'owner')->create();

    $this->postJson("/api/restaurants/{$restaurant->id}/service-schedules", [
        'day_of_week' => 9,
        'service_type' => 'lunch',
        'start_time' => '12:00:00',
        'end_time' => '14:30:00',
        'capacity' => 10,
        'max_party_size' => 4,
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('day_of_week');
});

it('updates a schedule', function () {
    $owner = actingAsRestaurateur();
    $schedule = ServiceSchedule::factory()
        ->for(Restaurant::factory()->for($owner, 'owner'))
        ->create(['capacity' => 30, 'max_party_size' => 6]);

    $this->patchJson("/api/service-schedules/{$schedule->id}", ['capacity' => 50])
        ->assertOk()
        ->assertJsonPath('data.capacity', 50);
});

it('rejects an update conflicting with another schedule', function () {
    $owner = actingAsRestaurateur();
    $restaurant = Restaurant::factory()->for($owner, 'owner')->create();
    ServiceSchedule::factory()->for($restaurant)->create(['day_of_week' => 1, 'service_type' => 'dinner']);
    $other = ServiceSchedule::factory()->for($restaurant)->create(['day_of_week' => 2, 'service_type' => 'dinner']);

    $this->patchJson("/api/service-schedules/{$other->id}", ['day_of_week' => 1])
        ->assertStatus(422)
        ->assertJsonValidationErrors('service_type');
});

it('rejects an update making the party size exceed the capacity', function () {
    $owner = actingAsRestaurateur();
    $schedule = ServiceSchedule::factory()
        ->for(Restaurant::factory()->for($owner, 'owner'))
        ->create(['capacity' => 10, 'max_party_size' => 5]);

    $this->patchJson("/api/service-schedules/{$schedule->id}", ['max_party_size' => 20])
        ->assertStatus(422)
        ->assertJsonValidationErrors('max_party_size');
});

it('deletes a schedule', function () {
    $owner = actingAsRestaurateur();
    $schedule = ServiceSchedule::factory()->for(Restaurant::factory()->for($owner, 'owner'))->create();

    $this->deleteJson("/api/service-schedules/{$schedule->id}")->assertNoContent();

    $this->assertDatabaseMissing('service_schedules', ['id' => $schedule->id]);
});

it('forbids managing a schedule of another owner', function () {
    actingAsRestaurateur();
    $foreign = ServiceSchedule::factory()->create();

    $this->patchJson("/api/service-schedules/{$foreign->id}", ['capacity' => 99])
        ->assertForbidden();
});
