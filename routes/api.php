<?php

use App\Http\Controllers\Account\ProfileController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Availability\AvailabilityController;
use App\Http\Controllers\Discovery\FavoriteController;
use App\Http\Controllers\Discovery\RestaurantDiscoveryController;
use App\Http\Controllers\FriendGroup\FriendGroupController;
use App\Http\Controllers\Menu\MenuCategoryController;
use App\Http\Controllers\Menu\MenuItemController;
use App\Http\Controllers\Menu\MenuItemMediaController;
use App\Http\Controllers\Menu\MenuItemOptionController;
use App\Http\Controllers\Menu\MenuItemOptionGroupController;
use App\Http\Controllers\Restaurant\RestaurantController;
use App\Http\Controllers\Restaurant\RestaurantMediaController;
use App\Http\Controllers\Schedule\ScheduleExceptionController;
use App\Http\Controllers\Schedule\ServiceScheduleController;
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

Route::get('/restaurants/{restaurant}/availabilities', [AvailabilityController::class, 'index'])
    ->name('availabilities.index');

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

    Route::get('/friend-groups', [FriendGroupController::class, 'index'])->name('friend-groups.index');
    Route::post('/friend-groups', [FriendGroupController::class, 'store'])->name('friend-groups.store');
    Route::get('/friend-groups/{friendGroup}', [FriendGroupController::class, 'show'])
        ->middleware('can:view,friendGroup')->name('friend-groups.show');
    Route::patch('/friend-groups/{friendGroup}', [FriendGroupController::class, 'update'])
        ->middleware('can:update,friendGroup')->name('friend-groups.update');
    Route::delete('/friend-groups/{friendGroup}', [FriendGroupController::class, 'destroy'])
        ->middleware('can:delete,friendGroup')->name('friend-groups.destroy');
    Route::put('/friend-groups/{friendGroup}/members', [FriendGroupController::class, 'syncMembers'])
        ->middleware('can:update,friendGroup')->name('friend-groups.members.sync');
    Route::delete('/friend-groups/{friendGroup}/members/{user}', [FriendGroupController::class, 'removeMember'])
        ->middleware('can:update,friendGroup')->whereNumber('user')->name('friend-groups.members.remove');

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

    Route::get('/restaurants/{restaurant}/service-schedules', [ServiceScheduleController::class, 'index'])
        ->middleware('can:view,restaurant')->name('service-schedules.index');
    Route::post('/restaurants/{restaurant}/service-schedules', [ServiceScheduleController::class, 'store'])
        ->middleware('can:update,restaurant')->name('service-schedules.store');
    Route::patch('/service-schedules/{serviceSchedule}', [ServiceScheduleController::class, 'update'])
        ->middleware('can:update,serviceSchedule')->name('service-schedules.update');
    Route::delete('/service-schedules/{serviceSchedule}', [ServiceScheduleController::class, 'destroy'])
        ->middleware('can:delete,serviceSchedule')->name('service-schedules.destroy');

    Route::get('/restaurants/{restaurant}/schedule-exceptions', [ScheduleExceptionController::class, 'index'])
        ->middleware('can:view,restaurant')->name('schedule-exceptions.index');
    Route::post('/restaurants/{restaurant}/schedule-exceptions', [ScheduleExceptionController::class, 'store'])
        ->middleware('can:update,restaurant')->name('schedule-exceptions.store');
    Route::patch('/schedule-exceptions/{scheduleException}', [ScheduleExceptionController::class, 'update'])
        ->middleware('can:update,scheduleException')->name('schedule-exceptions.update');
    Route::delete('/schedule-exceptions/{scheduleException}', [ScheduleExceptionController::class, 'destroy'])
        ->middleware('can:delete,scheduleException')->name('schedule-exceptions.destroy');

    Route::post('/restaurants/{restaurant}/availabilities/generate', [AvailabilityController::class, 'generate'])
        ->middleware('can:update,restaurant')->name('availabilities.generate');
});
