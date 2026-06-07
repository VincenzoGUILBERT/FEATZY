<?php

use App\Http\Controllers\Account\ProfileController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Restaurant\RestaurantController;
use App\Http\Controllers\Restaurant\RestaurantMediaController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register'])->name('register');
Route::post('/login', [AuthController::class, 'login'])->name('login');

Route::post('/forgot-password', [PasswordController::class, 'forgot'])->name('password.email');
Route::post('/reset-password', [PasswordController::class, 'reset'])->name('password.update');

Route::post('/email/verification-notification', [EmailVerificationController::class, 'resend'])
    ->middleware('throttle:6,1')
    ->name('verification.send');

Route::get('/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
    ->middleware('throttle:6,1')
    ->name('verification.verify');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', [AuthController::class, 'user'])->name('user.current');

    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::patch('/me', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/me/password', [ProfileController::class, 'updatePassword'])->name('password.change');
    Route::delete('/me', [ProfileController::class, 'destroy'])->name('account.destroy');

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
