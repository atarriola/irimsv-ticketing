<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Models\TicketAttachment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TicketAttachmentController extends Controller
{
    /**
     * Send an image attached to a ticket to someone allowed to see that ticket.
     */
    public function show(Ticket $ticket, TicketAttachment $attachment): StreamedResponse
    {
        Gate::authorize('view', $ticket);

        $disk = Storage::disk(TicketAttachment::DISK);

        abort_unless($disk->exists($attachment->path), 404);

        return $disk->response($attachment->path, $attachment->name);
    }

    /**
     * Take an image off a ticket, deleting its file.
     */
    public function destroy(Ticket $ticket, TicketAttachment $attachment): RedirectResponse
    {
        Gate::authorize('update', $ticket);

        $attachment->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'The image has been removed.']);

        return back();
    }
}
