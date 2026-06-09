<?php

use App\Http\Controllers\Schedule\ScheduleExceptionController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    // Dérogations datées (fermetures, horaires spéciaux, capacité réduite)
    Route::get('/restaurants/{restaurant}/schedule-exceptions', [ScheduleExceptionController::class, 'index'])
        ->middleware('can:view,restaurant')->name('schedule-exceptions.index');
    Route::post('/restaurants/{restaurant}/schedule-exceptions', [ScheduleExceptionController::class, 'store'])
        ->middleware('can:update,restaurant')->name('schedule-exceptions.store');
    Route::patch('/schedule-exceptions/{scheduleException}', [ScheduleExceptionController::class, 'update'])
        ->middleware('can:update,scheduleException')->name('schedule-exceptions.update');
    Route::delete('/schedule-exceptions/{scheduleException}', [ScheduleExceptionController::class, 'destroy'])
        ->middleware('can:delete,scheduleException')->name('schedule-exceptions.destroy');
});
