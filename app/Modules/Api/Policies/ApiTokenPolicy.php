<?php

namespace App\Modules\Api\Policies;

use App\Models\User;
use App\Modules\Api\Models\ApiToken;

/**
 * Every authenticated user manages their own tokens, and only those.
 */
class ApiTokenPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function delete(User $user, ApiToken $token): bool
    {
        return $token->user_id === $user->id;
    }
}
