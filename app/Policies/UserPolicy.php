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
     * Determine whether the user can edit the account's details and change its
     * password with the current one. Members' details are managed in LRMIS, so
     * only an administrator may, and only on their own account.
     */
    public function update(User $user, User $account): bool
    {
        return $user->isAdmin() && $user->is($account);
    }

    /**
     * Determine whether the user can set a new password on the account without
     * knowing the current one. Administrators change their own through update.
     */
    public function resetPassword(User $user, User $account): bool
    {
        return $user->isAdmin() && ! $user->is($account);
    }
}
