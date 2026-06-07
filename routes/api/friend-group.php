<?php

use App\Http\Controllers\FriendGroup\FriendGroupController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
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
});
