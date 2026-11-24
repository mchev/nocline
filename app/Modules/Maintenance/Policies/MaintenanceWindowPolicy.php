<?php

namespace App\Modules\Maintenance\Policies;

use App\Models\User;
use App\Modules\Maintenance\Models\MaintenanceWindow;

class MaintenanceWindowPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->canManage();
    }

    public function update(User $user, MaintenanceWindow $window): bool
    {
        return $user->canManage();
    }

    public function delete(User $user, MaintenanceWindow $window): bool
    {
        return $user->canManage();
    }
}
