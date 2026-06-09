<?php

use App\Http\Controllers\Reference\AllergenController;
use App\Http\Controllers\Reference\CuisineTypeController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    // Catalogue lecture seule des types de cuisine (sélecteurs restaurateur)
    Route::get('/cuisine-types', [CuisineTypeController::class, 'index'])->name('cuisine-types.index');
    // Catalogue lecture seule des allergènes (étiquetage des plats)
    Route::get('/allergens', [AllergenController::class, 'index'])->name('allergens.index');
});
