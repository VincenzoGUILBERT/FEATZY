<?php

namespace App\Policies;

use App\Models\Restaurant;
use App\Models\User;

class RestaurantPolicy
{
    public function view(User $user, Restaurant $restaurant): bool
    {
        return $restaurant->owner_id === $user->id;
    }

    public function update(User $user, Restaurant $restaurant): bool
    {
        return $restaurant->owner_id === $user->id;
    }

    public function delete(User $user, Restaurant $restaurant): bool
    {
        return $restaurant->owner_id === $user->id;
    }
}
