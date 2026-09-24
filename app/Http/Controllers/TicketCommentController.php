<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTicketCommentRequest;
use App\Http\Resources\TicketCommentResource;
use App\Models\Ticket;
use App\Models\TicketComment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class TicketCommentController extends Controller
{
    /**
     * Return the ticket's conversation, optionally only the messages after a given one.
     */
    public function index(Request $request, Ticket $ticket): JsonResponse
    {
        Gate::authorize('view', $ticket);

        $comments = $ticket->comments()
            ->with('author:id,name,role')
            ->when($request->integer('after') > 0, fn (Builder $query) => $query->where('id', '>', $request->integer('after')))
            ->oldest()
            ->oldest('id')
            ->get();

        return response()->json([
            'data' => $comments->map(fn (TicketComment $comment): array => TicketCommentResource::make($comment)->resolve($request)),
            'can_comment' => $request->user()->can('comment', $ticket),
        ]);
    }

    /**
     * Post a message in a ticket's conversation and return it.
     */
    public function store(StoreTicketCommentRequest $request, Ticket $ticket): JsonResponse
    {
        $comment = $ticket->comments()->create([
            'user_id' => $request->user()->id,
            'body' => $request->validated('body'),
        ]);

        $comment->load('author:id,name,role');

        return response()->json(['comment' => TicketCommentResource::make($comment)->resolve($request)], 201);
    }

    /**
     * Delete a message.
     */
    public function destroy(TicketComment $comment): JsonResponse
    {
        Gate::authorize('delete', $comment);

        $comment->delete();

        return response()->json(['id' => $comment->id]);
    }
}
