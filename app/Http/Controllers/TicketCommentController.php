<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTicketCommentRequest;
use App\Http\Requests\UpdateTicketCommentRequest;
use App\Http\Resources\TicketCommentResource;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\User;
use App\Notifications\TicketCommented;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
            ->visibleTo($request->user())
            ->with(['author:'.User::DISPLAY_COLUMNS, 'attachments'])
            ->when($request->integer('after') > 0, fn (Builder $query) => $query->where('id', '>', $request->integer('after')))
            ->oldest()
            ->oldest('id')
            ->get()
            ->each(fn (TicketComment $comment) => $comment->setRelation('ticket', $ticket));

        return response()->json([
            'data' => $comments->map(fn (TicketComment $comment): array => TicketCommentResource::make($comment)->resolve($request)),
            'can_comment' => $request->user()->can('comment', $ticket),
        ]);
    }

    /**
     * Post a message, or an internal note, in a ticket's conversation and return it.
     */
    public function store(StoreTicketCommentRequest $request, Ticket $ticket): JsonResponse
    {
        $user = $request->user();

        $comment = DB::transaction(function () use ($request, $ticket, $user): TicketComment {
            $comment = $ticket->comments()->create([
                'user_id' => $user->id,
                'body' => $request->validated('body'),
                'is_internal' => $request->boolean('is_internal'),
            ]);

            foreach ($request->validated('attachments') ?? [] as $file) {
                $comment->addAttachment($file, $user);
            }

            return $comment;
        });

        $comment->setRelation('ticket', $ticket)->load(['author:'.User::DISPLAY_COLUMNS, 'attachments']);

        if (! $comment->is_internal) {
            $this->notifyReaders($ticket, $comment);
        }

        return response()->json(['comment' => TicketCommentResource::make($comment)->resolve($request)], 201);
    }

    /**
     * Tell everyone following the ticket about the message, and the helpdesk when it came from a member.
     *
     * The writer is never told about their own message, one person is told once, and a failure
     * to notify someone is reported without stopping the message from being posted.
     */
    private function notifyReaders(Ticket $ticket, TicketComment $comment): void
    {
        $recipients = $ticket->audience();

        if (! $comment->author->isAdmin()) {
            $recipients = $recipients->merge(User::administrators()->get());
        }

        $recipients
            ->unique('id')
            ->reject(fn (User $recipient): bool => $recipient->is($comment->author))
            ->each(fn (User $recipient) => rescue(fn () => $recipient->notify(new TicketCommented($ticket, $comment))));
    }

    /**
     * Change the wording of a message and return it.
     */
    public function update(UpdateTicketCommentRequest $request, TicketComment $comment): JsonResponse
    {
        $comment->update(['body' => $request->validated('body')]);

        $comment->load(['author:'.User::DISPLAY_COLUMNS, 'attachments']);

        return response()->json(['comment' => TicketCommentResource::make($comment)->resolve($request)]);
    }

    /**
     * Delete a message together with its images.
     */
    public function destroy(TicketComment $comment): JsonResponse
    {
        Gate::authorize('delete', $comment);

        $comment->delete();

        return response()->json(['id' => $comment->id]);
    }
}
