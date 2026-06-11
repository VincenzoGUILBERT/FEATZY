<?php

use App\Http\Controllers\Account\AvatarController;
use App\Http\Controllers\Account\ProfileController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    // Mise à jour du profil
    Route::patch('/me', [ProfileController::class, 'update'])->name('profile.update');
    // Changement de mot de passe
    Route::put('/me/password', [ProfileController::class, 'updatePassword'])->name('password.change');
    // Suppression du compte
    Route::delete('/me', [ProfileController::class, 'destroy'])->name('account.destroy');
    // Photo de profil (avatar)
    Route::post('/me/avatar', [AvatarController::class, 'store'])->name('avatar.store');
    Route::delete('/me/avatar', [AvatarController::class, 'destroy'])->name('avatar.destroy');
    // Préférences alimentaires
    Route::put('/me/dietary-preferences', [ProfileController::class, 'updateDietaryPreferences'])->name('dietary-preferences.update');
    // Préférences de notifications
    Route::put('/me/notification-preferences', [ProfileController::class, 'updateNotificationPreferences'])->name('notification-preferences.update');
});
