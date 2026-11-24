<?php

namespace App\Modules\Alertes\Policies;

use App\Models\User;

class NotificationChannelPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canManage();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user): bool
    {
        return $user->isAdmin();
    }
}
