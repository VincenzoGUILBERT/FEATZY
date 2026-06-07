<?php

use App\Http\Controllers\Reservation\ReservationController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    // Réservations organisées par l'utilisateur connecté
    Route::get('/me/reservations', [ReservationController::class, 'index'])->name('reservations.index');
    // Création d'une réservation sur un créneau d'un restaurant
    Route::post('/restaurants/{restaurant}/reservations', [ReservationController::class, 'store'])
        ->middleware('role:client')->name('reservations.store');
    // Détail d'une réservation (organisateur, participant ou propriétaire du restaurant)
    Route::get('/reservations/{reservation}', [ReservationController::class, 'show'])
        ->middleware('can:view,reservation')->name('reservations.show');
    // Annulation d'une réservation (organisateur)
    Route::post('/reservations/{reservation}/cancel', [ReservationController::class, 'cancel'])
        ->middleware('can:cancel,reservation')->name('reservations.cancel');
});
