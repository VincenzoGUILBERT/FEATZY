<?php

namespace App\Actions\Account;

use App\Data\Account\ProfileData;
use App\Models\User;

class UpdateProfileAction
{
    public function handle(User $user, ProfileData $data): User
    {
        $user->update([
            'first_name' => $data->first_name,
            'last_name' => $data->last_name,
            'phone' => $data->phone,
        ]);

        return $user->load('roles');
    }
}
