<?php

namespace App\Policies;

use App\Models\ForumTopic;
use App\Models\User;

class ForumTopicPolicy
{
    /**
     * Determine whether the user can manage the list of topics.
     */
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can create topics.
     */
    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can edit the topic.
     */
    public function update(User $user, ForumTopic $forumTopic): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can delete the topic.
     */
    public function delete(User $user, ForumTopic $forumTopic): bool
    {
        return $user->isAdmin();
    }
}
