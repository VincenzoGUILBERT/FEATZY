<?php

use App\Models\Restaurant;
use App\Models\Service;

beforeEach(function () {
    $this->withHeader('Origin', config('app.frontend_url'));
});

it('lists the services of the owner restaurant', function () {
    $owner = actingAsRestaurateur();
    $restaurant = Restaurant::factory()->for($owner, 'owner')->create();
    Service::factory()->for($restaurant)->lunch()->create(['position' => 0]);
    Service::factory()->for($restaurant)->dinner()->create(['position' => 1]);

    $this->getJson("/api/restaurants/{$restaurant->id}/services")
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.type', 'lunch')
        ->assertJsonPath('data.1.type', 'dinner');
});

it('creates a service and persists it', function () {
    $owner = actingAsRestaurateur();
    $restaurant = Restaurant::factory()->for($owner, 'owner')->create();

    $this->postJson("/api/restaurants/{$restaurant->id}/services", [
        'name' => 'Déjeuner',
        'type' => 'lunch',
        'max_simultaneous_covers' => 40,
        'max_covers_per_slot' => 8,
    ])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Déjeuner')
        ->assertJsonPath('data.type', 'lunch')
        ->assertJsonPath('data.max_simultaneous_covers', 40)
        ->assertJsonPath('data.max_covers_per_slot', 8);

    $this->assertDatabaseHas('services', [
        'restaurant_id' => $restaurant->id,
        'name' => 'Déjeuner',
        'type' => 'lunch',
        'max_simultaneous_covers' => 40,
        'max_covers_per_slot' => 8,
    ]);
});

it('rejects a per-slot cap greater than the simultaneous cap', function () {
    $owner = actingAsRestaurateur();
    $restaurant = Restaurant::factory()->for($owner, 'owner')->create();

    $this->postJson("/api/restaurants/{$restaurant->id}/services", [
        'name' => 'Déjeuner',
        'type' => 'lunch',
        'max_simultaneous_covers' => 8,
        'max_covers_per_slot' => 40,
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('max_covers_per_slot');
});

it('shows a service of the owner', function () {
    $owner = actingAsRestaurateur();
    $service = Service::factory()
        ->for(Restaurant::factory()->for($owner, 'owner'))
        ->lunch()
        ->create();

    $this->getJson("/api/services/{$service->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $service->id)
        ->assertJsonPath('data.type', 'lunch');
});

it('updates a service', function () {
    $owner = actingAsRestaurateur();
    $service = Service::factory()
        ->for(Restaurant::factory()->for($owner, 'owner'))
        ->create(['is_active' => true]);

    $this->patchJson("/api/services/{$service->id}", ['is_active' => false])
        ->assertOk()
        ->assertJsonPath('data.is_active', false);

    expect($service->fresh()->is_active)->toBeFalse();
});

it('soft-deletes a service', function () {
    $owner = actingAsRestaurateur();
    $service = Service::factory()
        ->for(Restaurant::factory()->for($owner, 'owner'))
        ->create();

    $this->deleteJson("/api/services/{$service->id}")->assertNoContent();

    $this->assertSoftDeleted($service);
});

it('forbids a non-owner from managing services', function () {
    actingAsRestaurateur();
    $foreign = Restaurant::factory()->create();
    $foreignService = Service::factory()->for($foreign)->create();

    // index + store passent par les policies du restaurant.
    $this->getJson("/api/restaurants/{$foreign->id}/services")->assertForbidden();
    $this->postJson("/api/restaurants/{$foreign->id}/services", [
        'name' => 'Pirate',
        'type' => 'lunch',
        'max_simultaneous_covers' => 40,
        'max_covers_per_slot' => 8,
    ])->assertForbidden();

    // show + update + delete passent par les policies du service.
    $this->getJson("/api/services/{$foreignService->id}")->assertForbidden();
    $this->patchJson("/api/services/{$foreignService->id}", ['is_active' => false])->assertForbidden();
    $this->deleteJson("/api/services/{$foreignService->id}")->assertForbidden();
});

it('requires authentication', function () {
    $restaurant = Restaurant::factory()->create();
    $service = Service::factory()->for($restaurant)->create();

    $this->getJson("/api/restaurants/{$restaurant->id}/services")->assertUnauthorized();
    $this->postJson("/api/restaurants/{$restaurant->id}/services", [])->assertUnauthorized();
    $this->getJson("/api/services/{$service->id}")->assertUnauthorized();
    $this->patchJson("/api/services/{$service->id}", [])->assertUnauthorized();
    $this->deleteJson("/api/services/{$service->id}")->assertUnauthorized();
});
