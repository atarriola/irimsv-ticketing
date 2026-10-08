<?php

namespace App\Http\Controllers;

use App\Enums\TicketType;
use App\Http\Requests\UpdateTicketWatchRequest;
use App\Models\Ticket;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class TicketWatchController extends Controller
{
    /**
     * Follow a ticket, and say whether it affects the signed-in user too.
     */
    public function update(UpdateTicketWatchRequest $request, Ticket $ticket): RedirectResponse
    {
        $isAffected = $request->boolean('is_affected');

        $ticket->watchers()->syncWithoutDetaching([$request->user()->id => ['is_affected' => $isAffected]]);

        Inertia::flash('toast', ['type' => 'success', 'message' => match (true) {
            $isAffected && $ticket->type === TicketType::FeatureRequest => 'Your support for this request has been counted. You will be told when it moves along.',
            $isAffected => 'Noted that this affects you too. You will be told when it moves along.',
            default => 'You are now following this ticket.',
        }]);

        return back();
    }

    /**
     * Stop following a ticket.
     */
    public function destroy(Ticket $ticket): RedirectResponse
    {
        Gate::authorize('watch', $ticket);

        $ticket->watchers()->detach(request()->user()->id);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'You are no longer following this ticket.']);

        return back();
    }
}
