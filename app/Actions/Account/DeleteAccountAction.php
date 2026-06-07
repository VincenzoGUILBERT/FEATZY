<?php

namespace App\Actions\Account;

use App\Models\User;

class DeleteAccountAction
{
    public function handle(User $user): void
    {
        $user->delete();
    }
}
