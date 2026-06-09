<?php

use App\Models\Restaurant;
use App\Models\ScheduleException;
use App\Models\Service;
use Carbon\CarbonImmutable;

beforeEach(function () {
    $this->withHeader('Origin', config('app.frontend_url'));
    CarbonImmutable::setTestNow('2026-06-15 09:00:00');
});

afterEach(function () {
    CarbonImmutable::setTestNow();
});

it('lists the exceptions of an owned restaurant', function () {
    $owner = actingAsRestaurateur();
    $restaurant = Restaurant::factory()->for($owner, 'owner')->create();
    ScheduleException::factory()->for($restaurant)->closed()->create(['date' => '2026-07-01']);
    ScheduleException::factory()->for($restaurant)->closed()->create(['date' => '2026-07-08']);

    $this->getJson("/api/restaurants/{$restaurant->id}/schedule-exceptions")
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.date', '2026-07-01');
});

it('stores a closed exception', function () {
    $owner = actingAsRestaurateur();
    $restaurant = Restaurant::factory()->for($owner, 'owner')->create();

    $this->postJson("/api/restaurants/{$restaurant->id}/schedule-exceptions", [
        'date' => '2026-07-01',
        'type' => 'closed',
        'reason' => 'Jour férié',
    ])
        ->assertCreated()
        ->assertJsonPath('data.date', '2026-07-01')
        ->assertJsonPath('data.type', 'closed')
        ->assertJsonPath('data.service_id', null);
});

it('stores a special-hours exception with its window', function () {
    $owner = actingAsRestaurateur();
    $restaurant = Restaurant::factory()->for($owner, 'owner')->create();

    $this->postJson("/api/restaurants/{$restaurant->id}/schedule-exceptions", [
        'date' => '2026-07-01',
        'type' => 'special_hours',
        'opens_at' => '18:00:00',
        'last_seating_at' => '21:00:00',
        'closes_at' => '22:30:00',
    ])
        ->assertCreated()
        ->assertJsonPath('data.type', 'special_hours')
        ->assertJsonPath('data.opens_at', '18:00:00');
});

it('rejects a special-hours exception without its window', function () {
    $owner = actingAsRestaurateur();
    $restaurant = Restaurant::factory()->for($owner, 'owner')->create();

    $this->postJson("/api/restaurants/{$restaurant->id}/schedule-exceptions", [
        'date' => '2026-07-01',
        'type' => 'special_hours',
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['opens_at', 'last_seating_at', 'closes_at']);
});

it('stores a reduced-capacity exception with a pacing override', function () {
    $owner = actingAsRestaurateur();
    $restaurant = Restaurant::factory()->for($owner, 'owner')->create();

    $this->postJson("/api/restaurants/{$restaurant->id}/schedule-exceptions", [
        'date' => '2026-07-01',
        'type' => 'reduced_capacity',
        'pacing_override' => 4,
    ])
        ->assertCreated()
        ->assertJsonPath('data.type', 'reduced_capacity')
        ->assertJsonPath('data.pacing_override', 4);
});

it('rejects a reduced-capacity exception without any override', function () {
    $owner = actingAsRestaurateur();
    $restaurant = Restaurant::factory()->for($owner, 'owner')->create();

    $this->postJson("/api/restaurants/{$restaurant->id}/schedule-exceptions", [
        'date' => '2026-07-01',
        'type' => 'reduced_capacity',
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('capacity_override');
});

it('stores an exception targeting a specific service', function () {
    $owner = actingAsRestaurateur();
    $restaurant = Restaurant::factory()->for($owner, 'owner')->create();
    $service = Service::factory()->for($restaurant)->create();

    $this->postJson("/api/restaurants/{$restaurant->id}/schedule-exceptions", [
        'service_id' => $service->id,
        'date' => '2026-07-01',
        'type' => 'closed',
    ])
        ->assertCreated()
        ->assertJsonPath('data.service_id', $service->id);
});

it('rejects a duplicate exception for the same restaurant, service and date', function () {
    $owner = actingAsRestaurateur();
    $restaurant = Restaurant::factory()->for($owner, 'owner')->create();
    $service = Service::factory()->for($restaurant)->create();
    ScheduleException::factory()->for($restaurant)->forService($service)->closed()->create([
        'date' => '2026-07-01',
    ]);

    $this->postJson("/api/restaurants/{$restaurant->id}/schedule-exceptions", [
        'service_id' => $service->id,
        'date' => '2026-07-01',
        'type' => 'closed',
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('type');
});

it('updates an exception', function () {
    $owner = actingAsRestaurateur();
    $exception = ScheduleException::factory()
        ->for(Restaurant::factory()->for($owner, 'owner'))
        ->closed()
        ->create(['reason' => 'Old']);

    $this->patchJson("/api/schedule-exceptions/{$exception->id}", ['reason' => 'New'])
        ->assertOk()
        ->assertJsonPath('data.reason', 'New');
});

it('deletes an exception', function () {
    $owner = actingAsRestaurateur();
    $exception = ScheduleException::factory()
        ->for(Restaurant::factory()->for($owner, 'owner'))
        ->closed()
        ->create();

    $this->deleteJson("/api/schedule-exceptions/{$exception->id}")->assertNoContent();

    $this->assertDatabaseMissing('schedule_exceptions', ['id' => $exception->id]);
});

it('forbids a non-owner from managing an exception', function () {
    actingAsRestaurateur();
    $foreign = ScheduleException::factory()->closed()->create();

    $this->patchJson("/api/schedule-exceptions/{$foreign->id}", ['reason' => 'Hack'])
        ->assertForbidden();
});

it('requires authentication', function () {
    $restaurant = Restaurant::factory()->create();

    $this->postJson("/api/restaurants/{$restaurant->id}/schedule-exceptions", [
        'date' => '2026-07-01',
        'type' => 'closed',
    ])->assertUnauthorized();
});
