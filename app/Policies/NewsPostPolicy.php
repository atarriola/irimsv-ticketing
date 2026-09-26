<?php

namespace App\Policies;

use App\Models\NewsPost;
use App\Models\User;

class NewsPostPolicy
{
    /**
     * Determine whether the user can browse the news.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can read the post: anyone once it is published, administrators while it is a draft.
     */
    public function view(User $user, NewsPost $newsPost): bool
    {
        return $newsPost->isPublished() || $user->isAdmin();
    }

    /**
     * Determine whether the user can write posts.
     */
    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can edit the post.
     */
    public function update(User $user, NewsPost $newsPost): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can delete the post.
     */
    public function delete(User $user, NewsPost $newsPost): bool
    {
        return $user->isAdmin();
    }
}
