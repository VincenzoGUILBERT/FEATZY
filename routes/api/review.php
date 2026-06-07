<?php

use App\Http\Controllers\Review\ReviewController;
use Illuminate\Support\Facades\Route;

// Avis publics d'un restaurant publié (publiés uniquement, curseur)
Route::get('/discovery/restaurants/{restaurant}/reviews', [ReviewController::class, 'index'])
    ->name('discovery.restaurants.reviews.index');

Route::middleware('auth:sanctum')->group(function () {
    // Dépôt d'un avis sur une réservation complétée du client
    Route::post('/restaurants/{restaurant}/reviews', [ReviewController::class, 'store'])
        ->middleware('role:client')->name('restaurants.reviews.store');
    // Édition de son propre avis
    Route::patch('/reviews/{review}', [ReviewController::class, 'update'])
        ->middleware('can:update,review')->name('reviews.update');
    // Suppression de son propre avis
    Route::delete('/reviews/{review}', [ReviewController::class, 'destroy'])
        ->middleware('can:delete,review')->name('reviews.destroy');
});
