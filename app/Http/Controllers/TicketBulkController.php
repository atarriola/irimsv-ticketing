<?php

namespace App\Http\Controllers;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Http\Requests\BulkUpdateTicketsRequest;
use App\Models\Ticket;
use App\Models\User;
use App\Notifications\TicketStatusChanged;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class TicketBulkController extends Controller
{
    /**
     * Move several tickets to a status, or give them a priority, in one go.
     *
     * A status that does not belong to a ticket's workflow leaves that ticket alone.
     */
    public function update(BulkUpdateTicketsRequest $request): RedirectResponse
    {
        $user = $request->user();
        $status = $request->enum('status', TicketStatus::class);
        $priority = $request->enum('priority', TicketPriority::class);
        $changed = 0;

        Ticket::query()
            ->whereIn('id', $request->validated('ids'))
            ->with('requester')
            ->get()
            ->each(function (Ticket $ticket) use ($status, $priority, $user, &$changed): void {
                $touched = false;

                if ($status !== null && $ticket->status !== $status && in_array($status, TicketStatus::forType($ticket->type), true)) {
                    $ticket->markAs($status, $user);
                    $this->notifyAudience($ticket, $status, $user);
                    $touched = true;
                }

                if ($priority !== null && $ticket->priority !== $priority) {
                    $ticket->changePriority($priority, $user);
                    $touched = true;
                }

                if ($touched) {
                    $changed++;
                }
            });

        Inertia::flash('toast', ['type' => 'success', 'message' => $changed === 1 ? '1 ticket has been updated.' : "{$changed} tickets have been updated."]);

        return back();
    }

    /**
     * Tell the requester and the watchers of a ticket, except whoever made the change.
     */
    private function notifyAudience(Ticket $ticket, TicketStatus $status, User $actor): void
    {
        $ticket->audience()
            ->reject(fn (User $recipient): bool => $recipient->is($actor))
            ->each(fn (User $recipient) => rescue(fn () => $recipient->notify(new TicketStatusChanged($ticket, $status, $actor))));
    }
}
