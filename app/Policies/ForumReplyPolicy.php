<?php

namespace App\Policies;

use App\Models\ForumReply;
use App\Models\User;

class ForumReplyPolicy
{
    /**
     * Determine whether the user can edit the reply.
     */
    public function update(User $user, ForumReply $forumReply): bool
    {
        return $forumReply->user_id === $user->id;
    }

    /**
     * Determine whether the user can delete the reply.
     */
    public function delete(User $user, ForumReply $forumReply): bool
    {
        return $user->isAdmin() || $forumReply->user_id === $user->id;
    }
}
