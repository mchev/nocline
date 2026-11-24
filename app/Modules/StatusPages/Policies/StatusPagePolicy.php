<?php

namespace App\Modules\StatusPages\Policies;

use App\Models\User;
use App\Modules\StatusPages\Models\StatusPage;

class StatusPagePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->canManage();
    }

    public function update(User $user, StatusPage $page): bool
    {
        return $user->canManage();
    }

    public function delete(User $user, StatusPage $page): bool
    {
        return $user->canManage();
    }
}
