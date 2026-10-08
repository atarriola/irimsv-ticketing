<?php

namespace App\Policies;

use App\Enums\TicketStatus;
use App\Enums\TicketType;
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
     *
     * A ticket is private to the person who raised it and the administrators, unless
     * its requester shared it with everyone. A deleted ticket is only for administrators.
     */
    public function view(User $user, Ticket $ticket): bool
    {
        if ($ticket->trashed()) {
            return $user->isAdmin();
        }

        return $user->isAdmin() || $this->isRequester($user, $ticket) || $ticket->is_shared;
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
        if ($ticket->trashed()) {
            return false;
        }

        return $user->isAdmin()
            || ($this->isRequester($user, $ticket) && $ticket->status->isActive());
    }

    /**
     * Determine whether the user can delete the ticket.
     */
    public function delete(User $user, Ticket $ticket): bool
    {
        return $user->isAdmin() && ! $ticket->trashed();
    }

    /**
     * Determine whether the user can bring a deleted ticket back.
     */
    public function restore(User $user, Ticket $ticket): bool
    {
        return $user->isAdmin() && $ticket->trashed();
    }

    /**
     * Determine whether the user can move the ticket to any status.
     */
    public function changeStatus(User $user, Ticket $ticket): bool
    {
        return $user->isAdmin() && ! $ticket->trashed();
    }

    /**
     * Determine whether the user can confirm the fix or reopen the ticket: its requester, for a while after it was resolved.
     */
    public function confirmResolution(User $user, Ticket $ticket): bool
    {
        return $this->isRequester($user, $ticket) && ! $ticket->trashed() && $ticket->isAwaitingConfirmation();
    }

    /**
     * Determine whether the user can change the ticket's priority. The requester chooses one when raising it; after that it is the helpdesk's call.
     */
    public function changePriority(User $user, Ticket $ticket): bool
    {
        return $user->isAdmin() && ! $ticket->trashed();
    }

    /**
     * Determine whether the user can comment on the ticket.
     */
    public function comment(User $user, Ticket $ticket): bool
    {
        return $this->view($user, $ticket) && ! $ticket->trashed() && $ticket->status !== TicketStatus::Closed;
    }

    /**
     * Determine whether the user can leave a note only administrators see.
     */
    public function addInternalNote(User $user, Ticket $ticket): bool
    {
        return $user->isAdmin() && $this->comment($user, $ticket);
    }

    /**
     * Determine whether the user can follow the ticket. The requester is always told, so there is nothing for them to follow.
     */
    public function watch(User $user, Ticket $ticket): bool
    {
        return $this->view($user, $ticket) && ! $ticket->trashed() && ! $this->isRequester($user, $ticket);
    }

    /**
     * Determine whether the user can say how the help was: the requester, once the ticket is done.
     */
    public function rate(User $user, Ticket $ticket): bool
    {
        return $this->isRequester($user, $ticket) && ! $ticket->trashed() && $ticket->canBeRated();
    }

    /**
     * Determine whether the user can point a feature request at the release that delivered it.
     */
    public function linkRelease(User $user, Ticket $ticket): bool
    {
        return $user->isAdmin() && ! $ticket->trashed() && $ticket->type === TicketType::FeatureRequest;
    }

    /**
     * Determine whether the user can export tickets.
     */
    public function export(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user raised the ticket.
     */
    private function isRequester(User $user, Ticket $ticket): bool
    {
        return $ticket->user_id === $user->id;
    }
}
