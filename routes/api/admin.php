<?php

use App\Http\Controllers\Admin\AllergenController;
use App\Http\Controllers\Admin\CuisineTypeController;
use App\Http\Controllers\Admin\ReviewModerationController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'role:admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        // Modération des avis
        Route::get('/reviews', [ReviewModerationController::class, 'index'])->name('reviews.index');
        Route::post('/reviews/{review}/publish', [ReviewModerationController::class, 'publish'])->name('reviews.publish');
        Route::post('/reviews/{review}/hide', [ReviewModerationController::class, 'hide'])->name('reviews.hide');

        // Catalogue des types de cuisine
        Route::get('/cuisine-types', [CuisineTypeController::class, 'index'])->name('cuisine-types.index');
        Route::post('/cuisine-types', [CuisineTypeController::class, 'store'])->name('cuisine-types.store');
        Route::patch('/cuisine-types/{cuisineType}', [CuisineTypeController::class, 'update'])->name('cuisine-types.update');
        Route::delete('/cuisine-types/{cuisineType}', [CuisineTypeController::class, 'destroy'])->name('cuisine-types.destroy');

        // Catalogue des allergènes
        Route::get('/allergens', [AllergenController::class, 'index'])->name('allergens.index');
        Route::post('/allergens', [AllergenController::class, 'store'])->name('allergens.store');
        Route::patch('/allergens/{allergen}', [AllergenController::class, 'update'])->name('allergens.update');
        Route::delete('/allergens/{allergen}', [AllergenController::class, 'destroy'])->name('allergens.destroy');
    });
