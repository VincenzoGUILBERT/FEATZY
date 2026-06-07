<?php

use App\Http\Controllers\Restaurant\RestaurantController;
use App\Http\Controllers\Restaurant\RestaurantMediaController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me/restaurants', [RestaurantController::class, 'index'])
        ->middleware('role:restaurateur')->name('restaurants.index');
    Route::post('/restaurants', [RestaurantController::class, 'store'])
        ->middleware('role:restaurateur')->name('restaurants.store');

    Route::get('/restaurants/{restaurant}', [RestaurantController::class, 'show'])
        ->middleware('can:view,restaurant')->name('restaurants.show');
    Route::patch('/restaurants/{restaurant}', [RestaurantController::class, 'update'])
        ->middleware('can:update,restaurant')->name('restaurants.update');
    Route::delete('/restaurants/{restaurant}', [RestaurantController::class, 'destroy'])
        ->middleware('can:delete,restaurant')->name('restaurants.destroy');
    Route::post('/restaurants/{restaurant}/publish', [RestaurantController::class, 'publish'])
        ->middleware('can:update,restaurant')->name('restaurants.publish');

    Route::post('/restaurants/{restaurant}/media/{collection}', [RestaurantMediaController::class, 'store'])
        ->middleware('can:update,restaurant')
        ->whereIn('collection', ['logo', 'cover', 'gallery'])
        ->name('restaurants.media.store');
    Route::delete('/restaurants/{restaurant}/media/{media}', [RestaurantMediaController::class, 'destroy'])
        ->middleware('can:update,restaurant')
        ->whereNumber('media')
        ->name('restaurants.media.destroy');
});
