<?php

use App\Models\Restaurant;
use App\Models\ScheduleException;

beforeEach(function () {
    $this->withHeader('Origin', config('app.frontend_url'));
});

it('creates an all-day exception', function () {
    $owner = actingAsRestaurateur();
    $restaurant = Restaurant::factory()->for($owner, 'owner')->create();

    $this->postJson("/api/restaurants/{$restaurant->id}/schedule-exceptions", [
        'date' => '2026-07-01',
        'is_closed' => true,
        'reason' => 'Jour férié',
    ])
        ->assertCreated()
        ->assertJsonPath('data.date', '2026-07-01')
        ->assertJsonPath('data.service_type', null)
        ->assertJsonPath('data.is_closed', true);
});

it('creates a service-specific exception', function () {
    $owner = actingAsRestaurateur();
    $restaurant = Restaurant::factory()->for($owner, 'owner')->create();

    $this->postJson("/api/restaurants/{$restaurant->id}/schedule-exceptions", [
        'date' => '2026-07-01',
        'service_type' => 'lunch',
        'capacity' => 20,
    ])
        ->assertCreated()
        ->assertJsonPath('data.service_type', 'lunch')
        ->assertJsonPath('data.capacity', 20);
});

it('allows an all-day and a service-specific exception on the same date', function () {
    $owner = actingAsRestaurateur();
    $restaurant = Restaurant::factory()->for($owner, 'owner')->create();
    ScheduleException::factory()->for($restaurant)->create(['date' => '2026-07-01', 'service_type' => null]);

    $this->postJson("/api/restaurants/{$restaurant->id}/schedule-exceptions", [
        'date' => '2026-07-01',
        'service_type' => 'lunch',
    ])->assertCreated();
});

it('rejects a duplicate all-day exception', function () {
    $owner = actingAsRestaurateur();
    $restaurant = Restaurant::factory()->for($owner, 'owner')->create();
    ScheduleException::factory()->for($restaurant)->create(['date' => '2026-07-01', 'service_type' => null]);

    $this->postJson("/api/restaurants/{$restaurant->id}/schedule-exceptions", [
        'date' => '2026-07-01',
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('date');
});

it('rejects a duplicate service-specific exception', function () {
    $owner = actingAsRestaurateur();
    $restaurant = Restaurant::factory()->for($owner, 'owner')->create();
    ScheduleException::factory()->for($restaurant)->create(['date' => '2026-07-01', 'service_type' => 'dinner']);

    $this->postJson("/api/restaurants/{$restaurant->id}/schedule-exceptions", [
        'date' => '2026-07-01',
        'service_type' => 'dinner',
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('date');
});

it('rejects an end time before the start time', function () {
    $owner = actingAsRestaurateur();
    $restaurant = Restaurant::factory()->for($owner, 'owner')->create();

    $this->postJson("/api/restaurants/{$restaurant->id}/schedule-exceptions", [
        'date' => '2026-07-01',
        'service_type' => 'dinner',
        'start_time' => '22:00:00',
        'end_time' => '19:00:00',
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('end_time');
});

it('rejects a max party size greater than the capacity on create', function () {
    $owner = actingAsRestaurateur();
    $restaurant = Restaurant::factory()->for($owner, 'owner')->create();

    $this->postJson("/api/restaurants/{$restaurant->id}/schedule-exceptions", [
        'date' => '2026-07-01',
        'service_type' => 'lunch',
        'capacity' => 10,
        'max_party_size' => 20,
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('max_party_size');
});

it('rejects a max party size greater than the capacity on update', function () {
    $owner = actingAsRestaurateur();
    $exception = ScheduleException::factory()
        ->for(Restaurant::factory()->for($owner, 'owner'))
        ->create(['capacity' => 10, 'max_party_size' => 5]);

    $this->patchJson("/api/schedule-exceptions/{$exception->id}", ['max_party_size' => 20])
        ->assertStatus(422)
        ->assertJsonValidationErrors('max_party_size');
});

it('updates an exception', function () {
    $owner = actingAsRestaurateur();
    $exception = ScheduleException::factory()
        ->for(Restaurant::factory()->for($owner, 'owner'))
        ->create(['reason' => 'Old']);

    $this->patchJson("/api/schedule-exceptions/{$exception->id}", ['reason' => 'New'])
        ->assertOk()
        ->assertJsonPath('data.reason', 'New');
});

it('deletes an exception', function () {
    $owner = actingAsRestaurateur();
    $exception = ScheduleException::factory()->for(Restaurant::factory()->for($owner, 'owner'))->create();

    $this->deleteJson("/api/schedule-exceptions/{$exception->id}")->assertNoContent();

    $this->assertDatabaseMissing('schedule_exceptions', ['id' => $exception->id]);
});

it('forbids managing an exception of another owner', function () {
    actingAsRestaurateur();
    $foreign = ScheduleException::factory()->create();

    $this->patchJson("/api/schedule-exceptions/{$foreign->id}", ['reason' => 'Hack'])
        ->assertForbidden();
});
