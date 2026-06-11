<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Http\Requests\Account\UploadAvatarRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class AvatarController extends Controller
{
    /**
     * Upload (or replace) the authenticated user's avatar.
     */
    public function store(UploadAvatarRequest $request): UserResource
    {
        $user = $request->user();
        $user->addMediaFromRequest('file')->toMediaCollection('avatar');

        return UserResource::make($user->load('roles'));
    }

    /**
     * Remove the authenticated user's avatar.
     */
    public function destroy(Request $request): Response
    {
        $request->user()->clearMediaCollection('avatar');

        return response()->noContent();
    }
}
