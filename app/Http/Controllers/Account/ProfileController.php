<?php

namespace App\Http\Controllers\Account;

use App\Actions\Account\ChangePasswordAction;
use App\Actions\Account\DeleteAccountAction;
use App\Actions\Account\UpdateProfileAction;
use App\Data\Account\ProfileData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Account\ChangePasswordRequest;
use App\Http\Requests\Account\DeleteAccountRequest;
use App\Http\Requests\Account\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class ProfileController extends Controller
{
    public function update(UpdateProfileRequest $request, UpdateProfileAction $updateProfile): UserResource
    {
        $user = $updateProfile->handle(
            $request->user(),
            ProfileData::from($request->validated()),
        );

        return UserResource::make($user);
    }

    public function updatePassword(ChangePasswordRequest $request, ChangePasswordAction $changePassword): Response
    {
        $changePassword->handle($request->user(), $request->validated('password'));

        return response()->noContent();
    }

    public function destroy(DeleteAccountRequest $request, DeleteAccountAction $deleteAccount): Response
    {
        $deleteAccount->handle($request->user());

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->noContent();
    }
}
