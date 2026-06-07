<?php

use App\Http\Controllers\Discovery\FavoriteController;
use App\Http\Controllers\Discovery\RestaurantDiscoveryController;
use Illuminate\Support\Facades\Route;

Route::get('/discovery/restaurants', [RestaurantDiscoveryController::class, 'index'])
    ->name('discovery.restaurants.index');
Route::get('/discovery/restaurants/{restaurant}', [RestaurantDiscoveryController::class, 'show'])
    ->name('discovery.restaurants.show');
Route::get('/discovery/restaurants/{restaurant}/menu', [RestaurantDiscoveryController::class, 'menu'])
    ->name('discovery.restaurants.menu');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me/favorites', [FavoriteController::class, 'index'])->name('favorites.index');
    Route::put('/restaurants/{restaurant}/favorite', [FavoriteController::class, 'store'])->name('favorites.store');
    Route::delete('/restaurants/{restaurant}/favorite', [FavoriteController::class, 'destroy'])->name('favorites.destroy');
});
