<?php

use App\Exceptions\InvalidStatusTransitionException;
use App\Exceptions\Reservation\SlotUnavailableException;
use Illuminate\Support\Facades\Route;

it('renders a 409 domain exception as mapped JSON', function () {
    Route::middleware('api')->get('/api/_test/slot', function () {
        throw new SlotUnavailableException;
    });

    $this->getJson('/api/_test/slot')
        ->assertStatus(409)
        ->assertJsonPath('code', 'SLOT_UNAVAILABLE')
        ->assertJsonStructure(['message', 'code']);
});

it('renders a 422 domain exception with the offending transition message', function () {
    Route::middleware('api')->get('/api/_test/transition', function () {
        throw InvalidStatusTransitionException::between('confirmed', 'served');
    });

    $this->getJson('/api/_test/transition')
        ->assertStatus(422)
        ->assertJsonPath('code', 'INVALID_STATUS_TRANSITION')
        ->assertJsonPath('message', 'Cannot transition from "confirmed" to "served".');
});
