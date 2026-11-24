<?php

namespace App\Modules\Renewals\Policies;

use App\Models\User;

class RenewalPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function refresh(User $user): bool
    {
        return $user->canManage();
    }
}
