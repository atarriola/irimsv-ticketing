<?php

namespace App\Policies;

use App\Models\SavedReply;
use App\Models\User;

class SavedReplyPolicy
{
    /**
     * Determine whether the user can list and use saved replies.
     */
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can write saved replies.
     */
    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can edit the saved reply.
     */
    public function update(User $user, SavedReply $savedReply): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can delete the saved reply.
     */
    public function delete(User $user, SavedReply $savedReply): bool
    {
        return $user->isAdmin();
    }
}
