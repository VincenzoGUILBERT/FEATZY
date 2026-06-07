<?php

use App\Http\Controllers\Menu\MenuCategoryController;
use App\Http\Controllers\Menu\MenuItemController;
use App\Http\Controllers\Menu\MenuItemMediaController;
use App\Http\Controllers\Menu\MenuItemOptionController;
use App\Http\Controllers\Menu\MenuItemOptionGroupController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/restaurants/{restaurant}/menu-categories', [MenuCategoryController::class, 'index'])
        ->middleware('can:view,restaurant')->name('menu-categories.index');
    Route::post('/restaurants/{restaurant}/menu-categories', [MenuCategoryController::class, 'store'])
        ->middleware('can:update,restaurant')->name('menu-categories.store');
    Route::patch('/restaurants/{restaurant}/menu-categories/reorder', [MenuCategoryController::class, 'reorder'])
        ->middleware('can:update,restaurant')->name('menu-categories.reorder');
    Route::patch('/menu-categories/{menuCategory}', [MenuCategoryController::class, 'update'])
        ->middleware('can:update,menuCategory')->name('menu-categories.update');
    Route::delete('/menu-categories/{menuCategory}', [MenuCategoryController::class, 'destroy'])
        ->middleware('can:delete,menuCategory')->name('menu-categories.destroy');

    Route::get('/restaurants/{restaurant}/menu-items', [MenuItemController::class, 'index'])
        ->middleware('can:view,restaurant')->name('menu-items.index');
    Route::post('/restaurants/{restaurant}/menu-items', [MenuItemController::class, 'store'])
        ->middleware('can:update,restaurant')->name('menu-items.store');
    Route::get('/menu-items/{menuItem}', [MenuItemController::class, 'show'])
        ->middleware('can:view,menuItem')->name('menu-items.show');
    Route::patch('/menu-items/{menuItem}', [MenuItemController::class, 'update'])
        ->middleware('can:update,menuItem')->name('menu-items.update');
    Route::delete('/menu-items/{menuItem}', [MenuItemController::class, 'destroy'])
        ->middleware('can:delete,menuItem')->name('menu-items.destroy');
    Route::put('/menu-items/{menuItem}/allergens', [MenuItemController::class, 'syncAllergens'])
        ->middleware('can:update,menuItem')->name('menu-items.allergens.sync');
    Route::post('/menu-items/{menuItem}/photos', [MenuItemMediaController::class, 'store'])
        ->middleware('can:update,menuItem')->name('menu-items.photos.store');
    Route::delete('/menu-items/{menuItem}/photos/{media}', [MenuItemMediaController::class, 'destroy'])
        ->middleware('can:update,menuItem')->whereNumber('media')->name('menu-items.photos.destroy');

    Route::post('/menu-items/{menuItem}/option-groups', [MenuItemOptionGroupController::class, 'store'])
        ->middleware('can:update,menuItem')->name('menu-item-option-groups.store');
    Route::patch('/menu-item-option-groups/{optionGroup}', [MenuItemOptionGroupController::class, 'update'])
        ->middleware('can:update,optionGroup')->name('menu-item-option-groups.update');
    Route::delete('/menu-item-option-groups/{optionGroup}', [MenuItemOptionGroupController::class, 'destroy'])
        ->middleware('can:delete,optionGroup')->name('menu-item-option-groups.destroy');

    Route::post('/menu-item-option-groups/{optionGroup}/options', [MenuItemOptionController::class, 'store'])
        ->middleware('can:update,optionGroup')->name('menu-item-options.store');
    Route::patch('/menu-item-options/{menuItemOption}', [MenuItemOptionController::class, 'update'])
        ->middleware('can:update,menuItemOption')->name('menu-item-options.update');
    Route::delete('/menu-item-options/{menuItemOption}', [MenuItemOptionController::class, 'destroy'])
        ->middleware('can:delete,menuItemOption')->name('menu-item-options.destroy');
});
