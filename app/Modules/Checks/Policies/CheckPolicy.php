<?php

namespace App\Modules\Checks\Policies;

use App\Models\User;
use App\Modules\Checks\Models\Check;

class CheckPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Check $check): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->canManage();
    }

    public function update(User $user, Check $check): bool
    {
        return $user->canManage();
    }

    public function delete(User $user, Check $check): bool
    {
        return $user->isAdmin();
    }
}
