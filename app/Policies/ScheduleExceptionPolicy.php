<?php

namespace App\Policies;

use App\Models\ScheduleException;
use App\Models\User;

class ScheduleExceptionPolicy
{
    public function update(User $user, ScheduleException $scheduleException): bool
    {
        return $scheduleException->restaurant?->owner_id === $user->id;
    }

    public function delete(User $user, ScheduleException $scheduleException): bool
    {
        return $scheduleException->restaurant?->owner_id === $user->id;
    }
}
