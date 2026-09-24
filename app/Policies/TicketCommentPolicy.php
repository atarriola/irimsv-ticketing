<?php

namespace App\Policies;

use App\Models\TicketComment;
use App\Models\User;

class TicketCommentPolicy
{
    /**
     * Determine whether the user can edit the comment.
     */
    public function update(User $user, TicketComment $ticketComment): bool
    {
        return $ticketComment->user_id === $user->id;
    }

    /**
     * Determine whether the user can delete the comment.
     */
    public function delete(User $user, TicketComment $ticketComment): bool
    {
        return $user->isAdmin() || $ticketComment->user_id === $user->id;
    }
}
