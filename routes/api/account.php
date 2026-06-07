<?php

use App\Http\Controllers\Account\ProfileController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::patch('/me', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/me/password', [ProfileController::class, 'updatePassword'])->name('password.change');
    Route::delete('/me', [ProfileController::class, 'destroy'])->name('account.destroy');
});
