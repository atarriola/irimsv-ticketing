<?php

namespace App\Http\Controllers;

use App\Enums\TicketStatus;
use App\Http\Requests\UpdateTicketStatusRequest;
use App\Models\Ticket;
use App\Models\User;
use App\Notifications\TicketStatusChanged;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class TicketStatusController extends Controller
{
    /**
     * Move a ticket to another status and tell the people following it.
     */
    public function update(UpdateTicketStatusRequest $request, Ticket $ticket): RedirectResponse
    {
        $user = $request->user();
        $status = $request->enum('status', TicketStatus::class);
        $wasResolution = $ticket->status->isResolution();

        $ticket->markAs($status, $user);

        $this->notifyAudience($ticket, $status, $user);

        Inertia::flash('toast', ['type' => 'success', 'message' => match (true) {
            $wasResolution && $status === TicketStatus::Open => 'The ticket has been reopened.',
            $wasResolution && $status === TicketStatus::Closed => 'Thanks for confirming. The ticket is now closed.',
            default => "The ticket is now marked as {$status->label()}.",
        }]);

        return back();
    }

    /**
     * Tell the requester and the watchers, and the helpdesk when the requester made the change, except whoever did.
     *
     * A failure to notify someone is reported without stopping the change.
     */
    private function notifyAudience(Ticket $ticket, TicketStatus $status, User $actor): void
    {
        $recipients = $ticket->audience();

        if (! $actor->isAdmin()) {
            $recipients = $recipients->merge(User::administrators()->get());
        }

        $recipients
            ->unique('id')
            ->reject(fn (User $recipient): bool => $recipient->is($actor))
            ->each(fn (User $recipient) => rescue(fn () => $recipient->notify(new TicketStatusChanged($ticket, $status, $actor))));
    }
}
