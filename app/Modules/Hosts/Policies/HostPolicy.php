<?php

namespace App\Modules\Hosts\Policies;

use App\Models\User;
use App\Modules\Hosts\Models\Host;

class HostPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Host $host): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->canManage();
    }

    public function update(User $user, Host $host): bool
    {
        return $user->canManage();
    }

    public function delete(User $user, Host $host): bool
    {
        return $user->isAdmin();
    }
}
