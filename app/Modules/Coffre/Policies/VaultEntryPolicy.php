<?php

namespace App\Modules\Coffre\Policies;

use App\Models\User;
use App\Modules\Coffre\Models\VaultEntry;

class VaultEntryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canManage();
    }

    public function reveal(User $user, VaultEntry $entry): bool
    {
        return $user->canManage();
    }

    public function create(User $user): bool
    {
        return $user->canManage();
    }

    public function update(User $user, VaultEntry $entry): bool
    {
        return $user->canManage();
    }

    public function delete(User $user, VaultEntry $entry): bool
    {
        return $user->isAdmin();
    }

    public function audit(User $user): bool
    {
        return $user->isAdmin();
    }
}
