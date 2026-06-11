<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\SearchUsersRequest;
use App\Http\Resources\FriendMemberResource;
use App\Models\User;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class UserSearchController extends Controller
{
    /**
     * Search users by name, e-mail or phone to invite as guests or contacts.
     * Returns identity only (no e-mail/phone) and never the current user.
     */
    public function index(SearchUsersRequest $request): AnonymousResourceCollection
    {
        $term = '%'.$request->validated('q').'%';

        $users = User::query()
            ->whereKeyNot($request->user()->id)
            ->where(function ($query) use ($term): void {
                $query->where('first_name', 'like', $term)
                    ->orWhere('last_name', 'like', $term)
                    ->orWhere('email', 'like', $term)
                    ->orWhere('phone', 'like', $term);
            })
            ->orderBy('first_name')
            ->limit(10)
            ->get();

        return FriendMemberResource::collection($users);
    }
}
