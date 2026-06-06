<?php

namespace App\Actions\Auth;

use App\Data\Auth\RegisterUserData;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class RegisterUserAction
{
    /**
     * Register a new client account and trigger the email verification flow.
     */
    public function handle(RegisterUserData $data): User
    {
        $user = DB::transaction(function () use ($data): User {
            $user = User::create([
                'first_name' => $data->first_name,
                'last_name' => $data->last_name,
                'email' => $data->email,
                'phone' => $data->phone,
                'password' => Hash::make($data->password),
            ]);

            $user->assignRole(UserRole::Client->value);

            return $user;
        });

        event(new Registered($user));

        return $user->load('roles');
    }
}
