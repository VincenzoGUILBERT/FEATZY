<?php

use App\Http\Controllers\Availability\AvailabilityController;
use Illuminate\Support\Facades\Route;

// Créneaux réservables d'un restaurant à une date (public, calcul à la volée)
Route::get('/restaurants/{restaurant}/availability', [AvailabilityController::class, 'index'])
    ->name('availability.index');
