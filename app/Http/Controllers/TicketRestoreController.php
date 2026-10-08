<?php

namespace App\Http\Controllers;

use App\Enums\TicketEventKind;
use App\Models\Ticket;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class TicketRestoreController extends Controller
{
    /**
     * Bring a deleted ticket back.
     */
    public function update(Request $request, Ticket $ticket): RedirectResponse
    {
        Gate::authorize('restore', $ticket);

        $ticket->restore();
        $ticket->recordEvent(TicketEventKind::Restored, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$ticket->key} has been restored."]);

        return redirect()->route('tickets.show', $ticket);
    }
}
