<?php

namespace App\Http\Controllers;

use App\Enums\TicketEventKind;
use App\Http\Requests\UpdateTicketRatingRequest;
use App\Models\Ticket;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class TicketRatingController extends Controller
{
    /**
     * Record how the requester found the help on a finished ticket.
     */
    public function update(UpdateTicketRatingRequest $request, Ticket $ticket): RedirectResponse
    {
        $ticket->forceFill([
            'rating' => $request->integer('rating'),
            'rating_comment' => $request->validated('rating_comment'),
        ])->save();

        $ticket->recordEvent(TicketEventKind::Rated, $request->user(), ['rating' => $request->integer('rating')]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Thank you for your feedback.']);

        return back();
    }
}
