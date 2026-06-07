<?php

use App\Models\Restaurant;
use App\Models\ServiceAvailability;
use Carbon\CarbonImmutable;

beforeEach(function () {
    CarbonImmutable::setTestNow('2026-06-15 09:00:00');
});

afterEach(function () {
    CarbonImmutable::setTestNow();
});

it('lists upcoming availabilities of a published restaurant with remaining seats', function () {
    $restaurant = Restaurant::factory()->published()->create();
    ServiceAvailability::factory()->for($restaurant)->create([
        'date' => CarbonImmutable::today()->addDay()->toDateString(),
        'service_type' => 'dinner',
        'capacity' => 40,
        'booked_seats' => 10,
    ]);

    $this->getJson("/api/restaurants/{$restaurant->id}/availabilities")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.capacity', 40)
        ->assertJsonPath('data.0.booked_seats', 10)
        ->assertJsonPath('data.0.remaining_seats', 30);
});

it('filters by service type', function () {
    $restaurant = Restaurant::factory()->published()->create();
    $date = CarbonImmutable::today()->addDay()->toDateString();
    ServiceAvailability::factory()->for($restaurant)->create(['date' => $date, 'service_type' => 'lunch']);
    ServiceAvailability::factory()->for($restaurant)->create(['date' => $date, 'service_type' => 'dinner']);

    $this->getJson("/api/restaurants/{$restaurant->id}/availabilities?filter[service_type]=dinner")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.service_type', 'dinner');
});

it('filters by date', function () {
    $restaurant = Restaurant::factory()->published()->create();
    $first = CarbonImmutable::today()->addDay()->toDateString();
    $second = CarbonImmutable::today()->addDays(2)->toDateString();
    ServiceAvailability::factory()->for($restaurant)->create(['date' => $first, 'service_type' => 'dinner']);
    ServiceAvailability::factory()->for($restaurant)->create(['date' => $second, 'service_type' => 'dinner']);

    $this->getJson("/api/restaurants/{$restaurant->id}/availabilities?filter[date]={$first}")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.date', $first);
});

it('combines date and service type filters', function () {
    $restaurant = Restaurant::factory()->published()->create();
    $first = CarbonImmutable::today()->addDay()->toDateString();
    $second = CarbonImmutable::today()->addDays(2)->toDateString();
    ServiceAvailability::factory()->for($restaurant)->create(['date' => $first, 'service_type' => 'lunch']);
    ServiceAvailability::factory()->for($restaurant)->create(['date' => $first, 'service_type' => 'dinner']);
    ServiceAvailability::factory()->for($restaurant)->create(['date' => $second, 'service_type' => 'dinner']);

    $this->getJson("/api/restaurants/{$restaurant->id}/availabilities?filter[date]={$first}&filter[service_type]=dinner")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.date', $first)
        ->assertJsonPath('data.0.service_type', 'dinner');
});

it('hides past availabilities', function () {
    $restaurant = Restaurant::factory()->published()->create();
    ServiceAvailability::factory()->for($restaurant)->create([
        'date' => CarbonImmutable::today()->subDay()->toDateString(),
        'service_type' => 'dinner',
    ]);
    ServiceAvailability::factory()->for($restaurant)->create([
        'date' => CarbonImmutable::today()->addDay()->toDateString(),
        'service_type' => 'dinner',
    ]);

    $this->getJson("/api/restaurants/{$restaurant->id}/availabilities")
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('does not expose availabilities of a draft restaurant', function () {
    $restaurant = Restaurant::factory()->create();
    ServiceAvailability::factory()->for($restaurant)->create([
        'date' => CarbonImmutable::today()->addDay()->toDateString(),
    ]);

    $this->getJson("/api/restaurants/{$restaurant->id}/availabilities")
        ->assertNotFound();
});
