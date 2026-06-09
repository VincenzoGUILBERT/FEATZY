<?php

namespace App\Policies;

use App\Models\Service;
use App\Models\ServiceSchedule;
use App\Models\User;

class ServiceSchedulePolicy
{
    public function update(User $user, ServiceSchedule $serviceSchedule): bool
    {
        return $this->owns($user, $serviceSchedule);
    }

    public function delete(User $user, ServiceSchedule $serviceSchedule): bool
    {
        return $this->owns($user, $serviceSchedule);
    }

    /**
     * Propriété via le restaurant du service (requête, sans lazy-loading).
     */
    private function owns(User $user, ServiceSchedule $serviceSchedule): bool
    {
        return Service::query()
            ->whereKey($serviceSchedule->service_id)
            ->whereRelation('restaurant', 'owner_id', $user->id)
            ->exists();
    }
}
