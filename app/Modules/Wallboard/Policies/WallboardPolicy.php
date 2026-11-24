<?php

namespace App\Modules\Wallboard\Policies;

use App\Models\User;

class WallboardPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canManage();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user): bool
    {
        return $user->isAdmin();
    }

    public function regenerate(User $user): bool
    {
        return $user->isAdmin();
    }
}
