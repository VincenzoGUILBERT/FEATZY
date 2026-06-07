<?php

use App\Http\Controllers\Schedule\ScheduleExceptionController;
use App\Http\Controllers\Schedule\ServiceScheduleController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
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
});
