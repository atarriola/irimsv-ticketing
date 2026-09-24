<?php

namespace App\Policies;

use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\User;

class TicketPolicy
{
    /**
     * Determine whether the user can list tickets.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the ticket.
     */
    public function view(User $user, Ticket $ticket): bool
    {
        return $user->isAdmin() || $this->isRequester($user, $ticket);
    }

    /**
     * Determine whether the user can raise tickets.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can edit the ticket's details.
     */
    public function update(User $user, Ticket $ticket): bool
    {
        return $user->isAdmin()
            || ($this->isRequester($user, $ticket) && $ticket->status->isActive());
    }

    /**
     * Determine whether the user can delete the ticket.
     */
    public function delete(User $user, Ticket $ticket): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can move the ticket to another status.
     */
    public function changeStatus(User $user, Ticket $ticket): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can comment on the ticket.
     */
    public function comment(User $user, Ticket $ticket): bool
    {
        return $this->view($user, $ticket) && $ticket->status !== TicketStatus::Closed;
    }

    /**
     * Determine whether the user raised the ticket.
     */
    private function isRequester(User $user, Ticket $ticket): bool
    {
        return $ticket->user_id === $user->id;
    }
}
