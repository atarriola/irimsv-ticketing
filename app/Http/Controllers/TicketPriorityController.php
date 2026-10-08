<?php

namespace App\Http\Controllers;

use App\Enums\TicketPriority;
use App\Http\Requests\UpdateTicketPriorityRequest;
use App\Models\Ticket;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class TicketPriorityController extends Controller
{
    /**
     * Change a ticket's priority.
     */
    public function update(UpdateTicketPriorityRequest $request, Ticket $ticket): RedirectResponse
    {
        $priority = $request->enum('priority', TicketPriority::class);

        $ticket->changePriority($priority, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => "The priority is now {$priority->label()}."]);

        return back();
    }
}
