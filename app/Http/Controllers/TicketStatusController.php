<?php

namespace App\Http\Controllers;

use App\Enums\TicketStatus;
use App\Http\Requests\UpdateTicketStatusRequest;
use App\Models\Ticket;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class TicketStatusController extends Controller
{
    /**
     * Move a ticket to another status.
     */
    public function update(UpdateTicketStatusRequest $request, Ticket $ticket): RedirectResponse
    {
        $status = $request->enum('status', TicketStatus::class);

        $ticket->markAs($status);

        Inertia::flash('toast', ['type' => 'success', 'message' => "The ticket is now marked as {$status->label()}."]);

        return back();
    }
}
