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
     * Determine whether the user can create accounts.
     */
    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can edit the account.
     */
    public function update(User $user, User $model): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can change another account's role.
     */
    public function changeRole(User $user, User $model): bool
    {
        return $user->isAdmin() && $user->isNot($model);
    }

    /**
     * Determine whether the user can delete another account.
     */
    public function delete(User $user, User $model): bool
    {
        return $user->isAdmin() && $user->isNot($model);
    }
}
