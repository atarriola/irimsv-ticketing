<?php

namespace App\Policies;

use App\Models\ForumThread;
use App\Models\User;

class ForumThreadPolicy
{
    /**
     * Determine whether the user can browse the forum.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can read the thread.
     */
    public function view(User $user, ForumThread $forumThread): bool
    {
        return true;
    }

    /**
     * Determine whether the user can start threads.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can edit the thread.
     */
    public function update(User $user, ForumThread $forumThread): bool
    {
        return $user->isAdmin()
            || ($forumThread->user_id === $user->id && ! $forumThread->is_locked);
    }

    /**
     * Determine whether the user can delete the thread.
     */
    public function delete(User $user, ForumThread $forumThread): bool
    {
        return $user->isAdmin() || $forumThread->user_id === $user->id;
    }

    /**
     * Determine whether the user can pin or lock the thread.
     */
    public function moderate(User $user, ForumThread $forumThread): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can reply in the thread.
     */
    public function reply(User $user, ForumThread $forumThread): bool
    {
        return $user->isAdmin() || ! $forumThread->is_locked;
    }
}
