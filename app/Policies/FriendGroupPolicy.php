<?php

namespace App\Policies;

use App\Models\FriendGroup;
use App\Models\User;

class FriendGroupPolicy
{
    public function view(User $user, FriendGroup $friendGroup): bool
    {
        return $friendGroup->owner_id === $user->id;
    }

    public function update(User $user, FriendGroup $friendGroup): bool
    {
        return $friendGroup->owner_id === $user->id;
    }

    public function delete(User $user, FriendGroup $friendGroup): bool
    {
        return $friendGroup->owner_id === $user->id;
    }
}
