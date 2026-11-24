<?php

namespace App\Modules\Groups\Policies;

use App\Models\User;
use App\Modules\Groups\Models\Group;

class GroupPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->canManage();
    }

    public function update(User $user, Group $group): bool
    {
        return $user->canManage();
    }

    public function delete(User $user, Group $group): bool
    {
        return $user->canManage();
    }
}
