<?php

namespace App\Policies;

use App\Models\ScheduleException;
use App\Models\User;

class ScheduleExceptionPolicy
{
    public function update(User $user, ScheduleException $scheduleException): bool
    {
        return $this->owns($user, $scheduleException);
    }

    public function delete(User $user, ScheduleException $scheduleException): bool
    {
        return $this->owns($user, $scheduleException);
    }

    /**
     * Propriété via le restaurant (requête, sans lazy-loading).
     */
    private function owns(User $user, ScheduleException $scheduleException): bool
    {
        return $scheduleException->restaurant()->where('owner_id', $user->id)->exists();
    }
}
