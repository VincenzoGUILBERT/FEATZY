<?php

use App\Http\Controllers\Reference\AllergenController;
use App\Http\Controllers\Reference\CuisineTypeController;
use App\Http\Controllers\Reference\DietaryPreferenceController;
use Illuminate\Support\Facades\Route;

// Catalogues publics en lecture seule (filtres discovery, étiquetage des plats, préférences utilisateur)
Route::get('/cuisine-types', [CuisineTypeController::class, 'index'])->name('cuisine-types.index');
Route::get('/allergens', [AllergenController::class, 'index'])->name('allergens.index');
Route::get('/dietary-preferences', [DietaryPreferenceController::class, 'index'])->name('dietary-preferences.index');
