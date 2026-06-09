<?php

use App\Http\Controllers\Schedule\ServiceScheduleController;
use App\Http\Controllers\Service\ServiceController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    // Services d'un restaurant (périodes de repas configurables)
    Route::get('/restaurants/{restaurant}/services', [ServiceController::class, 'index'])
        ->middleware('can:view,restaurant')->name('services.index');
    Route::post('/restaurants/{restaurant}/services', [ServiceController::class, 'store'])
        ->middleware('can:update,restaurant')->name('services.store');
    Route::get('/services/{service}', [ServiceController::class, 'show'])
        ->middleware('can:view,service')->name('services.show');
    Route::patch('/services/{service}', [ServiceController::class, 'update'])
        ->middleware('can:update,service')->name('services.update');
    Route::delete('/services/{service}', [ServiceController::class, 'destroy'])
        ->middleware('can:delete,service')->name('services.destroy');

    // Horaires hebdomadaires d'un service
    Route::get('/services/{service}/service-schedules', [ServiceScheduleController::class, 'index'])
        ->middleware('can:view,service')->name('service-schedules.index');
    Route::post('/services/{service}/service-schedules', [ServiceScheduleController::class, 'store'])
        ->middleware('can:update,service')->name('service-schedules.store');
    Route::patch('/service-schedules/{serviceSchedule}', [ServiceScheduleController::class, 'update'])
        ->middleware('can:update,serviceSchedule')->name('service-schedules.update');
    Route::delete('/service-schedules/{serviceSchedule}', [ServiceScheduleController::class, 'destroy'])
        ->middleware('can:delete,serviceSchedule')->name('service-schedules.destroy');
});
