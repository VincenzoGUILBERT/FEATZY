<?php

namespace App\Policies;

use App\Models\ServiceSchedule;
use App\Models\User;

class ServiceSchedulePolicy
{
    public function update(User $user, ServiceSchedule $serviceSchedule): bool
    {
        return $serviceSchedule->restaurant?->owner_id === $user->id;
    }

    public function delete(User $user, ServiceSchedule $serviceSchedule): bool
    {
        return $serviceSchedule->restaurant?->owner_id === $user->id;
    }
}
