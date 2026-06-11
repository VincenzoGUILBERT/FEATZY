<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Http\Requests\Account\ChangePasswordRequest;
use App\Http\Requests\Account\DeleteAccountRequest;
use App\Http\Requests\Account\UpdateDietaryPreferencesRequest;
use App\Http\Requests\Account\UpdateNotificationPreferencesRequest;
use App\Http\Requests\Account\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class ProfileController extends Controller
{
    public function update(UpdateProfileRequest $request): UserResource
    {
        $user = $request->user();
        $user->update($request->validated());

        return UserResource::make($user->load('roles'));
    }

    public function updateDietaryPreferences(UpdateDietaryPreferencesRequest $request): UserResource
    {
        $user = $request->user();
        $user->update($request->validated());

        return UserResource::make($user->load('roles'));
    }

    public function updateNotificationPreferences(UpdateNotificationPreferencesRequest $request): UserResource
    {
        $user = $request->user();
        $merged = array_merge($user->notificationPreferences(), $request->validated());
        $user->update(['notification_preferences' => $merged]);

        return UserResource::make($user->load('roles'));
    }

    public function updatePassword(ChangePasswordRequest $request): Response
    {
        $user = $request->user();

        // Rotate the remember token so existing "remember me" cookies stop working.
        $user->password = $request->validated('password');
        $user->setRememberToken(Str::random(60));
        $user->save();

        return response()->noContent();
    }

    public function destroy(DeleteAccountRequest $request): Response
    {
        $request->user()->delete();

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->noContent();
    }
}
