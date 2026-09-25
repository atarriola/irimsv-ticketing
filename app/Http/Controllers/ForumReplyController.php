<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreForumReplyRequest;
use App\Http\Requests\UpdateForumReplyRequest;
use App\Http\Resources\ForumReplyResource;
use App\Models\ForumReply;
use App\Models\ForumThread;
use App\Models\User;
use App\Notifications\ForumCommentReplied;
use App\Notifications\ForumThreadCommented;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ForumReplyController extends Controller
{
    /**
     * Return one page of a thread's comments with their replies.
     */
    public function index(Request $request, ForumThread $thread): JsonResponse
    {
        Gate::authorize('view', $thread);

        $comments = $thread->comments()
            ->with(ForumThread::COMMENT_RELATIONS)
            ->paginate(ForumThread::COMMENTS_PER_PAGE);

        return response()->json([
            'data' => $comments->getCollection()->map(fn (ForumReply $comment): array => ForumReplyResource::make($comment)->resolve($request)),
            'next_page' => $comments->hasMorePages() ? $comments->currentPage() + 1 : null,
        ]);
    }

    /**
     * Post a comment or a reply on a thread.
     */
    public function store(StoreForumReplyRequest $request, ForumThread $thread): JsonResponse
    {
        $parent = $request->filled('parent_id') ? $thread->replies()->with('author')->find($request->integer('parent_id')) : null;

        $reply = DB::transaction(function () use ($request, $thread, $parent): ForumReply {
            $reply = $thread->replies()->create([
                'user_id' => $request->user()->id,
                'parent_id' => $parent?->parent_id ?? $parent?->id,
                'body' => $request->validated('body'),
            ]);

            $thread->recordActivity();

            return $reply;
        });

        $reply->load(['author:'.User::DISPLAY_COLUMNS, 'reactions'])->setRelation('children', $reply->newCollection());

        $this->notifyReaders($thread, $reply, $parent);

        return response()->json([
            'reply' => ForumReplyResource::make($reply)->resolve($request),
            ...$thread->conversationTotals(),
        ], 201);
    }

    /**
     * Tell the thread's author about the comment and, for a reply, the author of the comment it answers.
     *
     * The writer is never told about their own comment, one person is told once, and a failure to
     * notify someone is reported without stopping the comment from being posted.
     */
    private function notifyReaders(ForumThread $thread, ForumReply $reply, ?ForumReply $parent): void
    {
        $thread->loadMissing('author');

        collect([$parent?->author, $thread->author])
            ->filter()
            ->unique('id')
            ->reject(fn (User $recipient): bool => $recipient->is($reply->author))
            ->each(fn (User $recipient) => rescue(fn () => $recipient->notify(
                $parent !== null && $recipient->is($parent->author)
                    ? new ForumCommentReplied($thread, $reply)
                    : new ForumThreadCommented($thread, $reply),
            )));
    }

    /**
     * Display the form for editing a reply.
     */
    public function edit(ForumReply $reply): Response
    {
        Gate::authorize('update', $reply);

        return Inertia::render('Forum/EditReply', [
            'reply' => $reply->only(['id', 'body']),
            'thread' => $reply->thread->only(['id', 'excerpt']),
        ]);
    }

    /**
     * Update a reply.
     */
    public function update(UpdateForumReplyRequest $request, ForumReply $reply): RedirectResponse
    {
        $reply->update(['body' => $request->validated('body')]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Your comment has been updated.']);

        return redirect()->route('forum.threads.show', $reply->forum_thread_id);
    }

    /**
     * Delete a comment together with its replies, or a single reply, and return the new totals.
     */
    public function destroy(ForumReply $reply): JsonResponse
    {
        Gate::authorize('delete', $reply);

        $reply->delete();

        return response()->json($reply->thread->conversationTotals());
    }
}
