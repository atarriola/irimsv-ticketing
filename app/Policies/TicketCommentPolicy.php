<?php

namespace App\Policies;

use App\Models\TicketComment;
use App\Models\User;

class TicketCommentPolicy
{
    /**
     * Determine whether the user can read the comment: an internal note is for administrators only.
     */
    public function view(User $user, TicketComment $ticketComment): bool
    {
        return $user->can('view', $ticketComment->ticket) && (! $ticketComment->is_internal || $user->isAdmin());
    }

    /**
     * Determine whether the user can edit the comment: only its author, while the conversation is still open.
     */
    public function update(User $user, TicketComment $ticketComment): bool
    {
        return $ticketComment->user_id === $user->id && $user->can('comment', $ticketComment->ticket);
    }

    /**
     * Determine whether the user can delete the comment.
     */
    public function delete(User $user, TicketComment $ticketComment): bool
    {
        return $user->isAdmin() || $ticketComment->user_id === $user->id;
    }
}
