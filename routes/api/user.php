<?php

use App\Http\Controllers\User\UserSearchController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    // Recherche d'utilisateurs (invitation de convives, ajout de contacts).
    // Throttle pour limiter l'énumération de comptes par e-mail/téléphone.
    Route::get('/users/search', [UserSearchController::class, 'index'])
        ->middleware('throttle:30,1')
        ->name('users.search');
});
