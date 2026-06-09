<?php

namespace App\Policies;

use App\Models\Service;
use App\Models\User;

class ServicePolicy
{
    public function view(User $user, Service $service): bool
    {
        return $this->owns($user, $service);
    }

    public function update(User $user, Service $service): bool
    {
        return $this->owns($user, $service);
    }

    public function delete(User $user, Service $service): bool
    {
        return $this->owns($user, $service);
    }

    /**
     * Vérifie la propriété via une requête (jamais de lazy-loading de la relation).
     */
    private function owns(User $user, Service $service): bool
    {
        return $service->restaurant()->where('owner_id', $user->id)->exists();
    }
}
