<?php

namespace App\Http\Controllers\FriendGroup;

use App\Http\Controllers\Controller;
use App\Http\Requests\FriendGroup\StoreFriendGroupRequest;
use App\Http\Requests\FriendGroup\SyncFriendGroupMembersRequest;
use App\Http\Requests\FriendGroup\UpdateFriendGroupRequest;
use App\Http\Resources\FriendGroupResource;
use App\Models\FriendGroup;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class FriendGroupController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        return FriendGroupResource::collection(
            $request->user()->friendGroups()
                ->withCount('members')
                ->latest()
                ->paginate(),
        );
    }

    public function store(StoreFriendGroupRequest $request): JsonResponse
    {
        $group = $request->user()->friendGroups()->create($request->validated());

        return FriendGroupResource::make($group->loadCount('members'))
            ->response()
            ->setStatusCode(HttpResponse::HTTP_CREATED);
    }

    public function show(FriendGroup $friendGroup): FriendGroupResource
    {
        return FriendGroupResource::make($friendGroup->load('members')->loadCount('members'));
    }

    public function update(UpdateFriendGroupRequest $request, FriendGroup $friendGroup): FriendGroupResource
    {
        $friendGroup->update($request->validated());

        return FriendGroupResource::make($friendGroup->loadCount('members'));
    }

    public function destroy(FriendGroup $friendGroup): Response
    {
        $friendGroup->delete();

        return response()->noContent();
    }

    public function syncMembers(SyncFriendGroupMembersRequest $request, FriendGroup $friendGroup): FriendGroupResource
    {
        $friendGroup->members()->sync($request->validated('members'));

        return FriendGroupResource::make($friendGroup->load('members')->loadCount('members'));
    }

    public function removeMember(FriendGroup $friendGroup, User $user): Response
    {
        $friendGroup->members()->detach($user->id);

        return response()->noContent();
    }
}
