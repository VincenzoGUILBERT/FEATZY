<?php

use App\Models\Restaurant;
use App\Models\Service;
use App\Models\ServiceSchedule;
use App\Models\User;
use Carbon\CarbonImmutable;

beforeEach(function () {
    $this->withHeader('Origin', config('app.frontend_url'));
    CarbonImmutable::setTestNow('2026-06-15 09:00:00');
});

afterEach(function () {
    CarbonImmutable::setTestNow();
});

/**
 * Restaurant détenu par $owner + un service rattaché (intervalle de créneau = 15 min par défaut).
 *
 * @return array{0: Restaurant, 1: Service}
 */
function ownedRestaurantWithService(User $owner): array
{
    $restaurant = Restaurant::factory()->for($owner, 'owner')->create();
    $service = Service::factory()->for($restaurant)->create([
        'max_simultaneous_covers' => 40,
        'max_covers_per_slot' => 8,
    ]);

    return [$restaurant, $service];
}

it('lists the schedules of a service for the owner', function () {
    $owner = actingAsRestaurateur();
    [, $service] = ownedRestaurantWithService($owner);

    ServiceSchedule::factory()->for($service)->create(['day_of_week' => 1]);
    ServiceSchedule::factory()->for($service)->create(['day_of_week' => 5]);

    $this->getJson("/api/services/{$service->id}/service-schedules")
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.service_id', $service->id);
});

it('stores a valid schedule', function () {
    $owner = actingAsRestaurateur();
    [, $service] = ownedRestaurantWithService($owner);

    $this->postJson("/api/services/{$service->id}/service-schedules", [
        'day_of_week' => 1,
        'opens_at' => '12:00:00',
        'last_seating_at' => '13:30:00',
        'closes_at' => '15:00:00',
        'crosses_midnight' => false,
    ])
        ->assertCreated()
        ->assertJsonPath('data.service_id', $service->id)
        ->assertJsonPath('data.day_of_week', 1)
        ->assertJsonPath('data.opens_at', '12:00:00')
        ->assertJsonPath('data.last_seating_at', '13:30:00')
        ->assertJsonPath('data.closes_at', '15:00:00');

    $this->assertDatabaseHas('service_schedules', [
        'service_id' => $service->id,
        'day_of_week' => 1,
        'opens_at' => '12:00:00',
    ]);
});

it('rejects an opens_at not aligned on the slot interval', function () {
    $owner = actingAsRestaurateur();
    [, $service] = ownedRestaurantWithService($owner);

    // 12:07 n'est pas un multiple de 15 min depuis minuit → clé opens_at.
    $this->postJson("/api/services/{$service->id}/service-schedules", [
        'day_of_week' => 1,
        'opens_at' => '12:07:00',
        'last_seating_at' => '13:30:00',
        'closes_at' => '15:00:00',
        'crosses_midnight' => false,
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('opens_at');
});

it('rejects a last_seating_at before the opens_at without crossing midnight', function () {
    $owner = actingAsRestaurateur();
    [, $service] = ownedRestaurantWithService($owner);

    $this->postJson("/api/services/{$service->id}/service-schedules", [
        'day_of_week' => 1,
        'opens_at' => '19:00:00',
        'last_seating_at' => '12:00:00',
        'closes_at' => '15:00:00',
        'crosses_midnight' => false,
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('last_seating_at');
});

it('updates a schedule', function () {
    $owner = actingAsRestaurateur();
    [, $service] = ownedRestaurantWithService($owner);
    $schedule = ServiceSchedule::factory()->for($service)->create(['day_of_week' => 1]);

    $this->patchJson("/api/service-schedules/{$schedule->id}", [
        'last_seating_at' => '14:00:00',
    ])
        ->assertOk()
        ->assertJsonPath('data.id', $schedule->id)
        ->assertJsonPath('data.last_seating_at', '14:00:00');

    $this->assertDatabaseHas('service_schedules', [
        'id' => $schedule->id,
        'last_seating_at' => '14:00:00',
    ]);
});

it('deletes a schedule', function () {
    $owner = actingAsRestaurateur();
    [, $service] = ownedRestaurantWithService($owner);
    $schedule = ServiceSchedule::factory()->for($service)->create();

    $this->deleteJson("/api/service-schedules/{$schedule->id}")
        ->assertNoContent();

    $this->assertDatabaseMissing('service_schedules', ['id' => $schedule->id]);
});

it('forbids a non-owner from managing schedules', function () {
    // Le service appartient à un autre propriétaire.
    $foreignOwner = User::factory()->restaurateur()->create();
    [, $service] = ownedRestaurantWithService($foreignOwner);
    $schedule = ServiceSchedule::factory()->for($service)->create();

    // L'utilisateur authentifié n'est pas le propriétaire.
    actingAsRestaurateur();

    $this->postJson("/api/services/{$service->id}/service-schedules", [
        'day_of_week' => 2,
        'opens_at' => '12:00:00',
        'last_seating_at' => '13:30:00',
        'closes_at' => '15:00:00',
        'crosses_midnight' => false,
    ])->assertForbidden();

    $this->patchJson("/api/service-schedules/{$schedule->id}", [
        'last_seating_at' => '14:00:00',
    ])->assertForbidden();

    $this->deleteJson("/api/service-schedules/{$schedule->id}")
        ->assertForbidden();
});

it('requires authentication', function () {
    $owner = User::factory()->restaurateur()->create();
    [, $service] = ownedRestaurantWithService($owner);
    $schedule = ServiceSchedule::factory()->for($service)->create();

    $this->postJson("/api/services/{$service->id}/service-schedules", [
        'day_of_week' => 1,
        'opens_at' => '12:00:00',
        'last_seating_at' => '13:30:00',
        'closes_at' => '15:00:00',
        'crosses_midnight' => false,
    ])->assertUnauthorized();

    $this->patchJson("/api/service-schedules/{$schedule->id}", [
        'last_seating_at' => '14:00:00',
    ])->assertUnauthorized();

    $this->deleteJson("/api/service-schedules/{$schedule->id}")
        ->assertUnauthorized();
});
