<?php

namespace App\Actions\Account;

use App\Models\User;
use Illuminate\Support\Str;

class ChangePasswordAction
{
    /**
     * Set a new password (hashed via the model cast) and rotate the remember
     * token so existing "remember me" cookies can no longer authenticate.
     */
    public function handle(User $user, string $newPassword): void
    {
        $user->password = $newPassword;
        $user->setRememberToken(Str::random(60));
        $user->save();
    }
}
