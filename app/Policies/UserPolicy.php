<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    /**
     * Determine whether the user can list accounts.
     */
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can change another account's helpdesk role.
     */
    public function changeRole(User $user, User $model): bool
    {
        return $user->isAdmin() && $user->isNot($model);
    }
}
