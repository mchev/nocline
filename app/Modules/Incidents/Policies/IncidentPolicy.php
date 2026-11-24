<?php

namespace App\Modules\Incidents\Policies;

use App\Models\User;
use App\Modules\Incidents\Models\Incident;

class IncidentPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function acknowledge(User $user, Incident $incident): bool
    {
        return $user->canManage();
    }
}
