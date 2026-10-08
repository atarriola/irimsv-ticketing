<?php

namespace App\Http\Controllers;

use App\Enums\TicketEventKind;
use App\Http\Requests\UpdateTicketReleaseRequest;
use App\Models\NewsPost;
use App\Models\Ticket;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class TicketReleaseController extends Controller
{
    /**
     * Point a feature request at the release post that delivered it, or remove the link.
     */
    public function update(UpdateTicketReleaseRequest $request, Ticket $ticket): RedirectResponse
    {
        $post = $request->filled('news_post_id') ? NewsPost::find($request->integer('news_post_id')) : null;

        $ticket->newsPost()->associate($post);
        $ticket->save();

        $ticket->recordEvent(TicketEventKind::ReleaseLinked, $request->user(), $post === null ? [] : ['title' => $post->title]);

        Inertia::flash('toast', ['type' => 'success', 'message' => $post === null
            ? 'The release link has been removed.'
            : "The request is now linked to \"{$post->title}\"."]);

        return back();
    }
}
