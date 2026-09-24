<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreForumReplyRequest;
use App\Http\Requests\UpdateForumReplyRequest;
use App\Http\Resources\ForumReplyResource;
use App\Models\ForumReply;
use App\Models\ForumThread;
use App\Models\User;
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
        $parent = $request->filled('parent_id') ? $thread->replies()->find($request->integer('parent_id')) : null;

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

        return response()->json([
            'reply' => ForumReplyResource::make($reply)->resolve($request),
            ...$this->totals($thread),
        ], 201);
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

        return response()->json($this->totals($reply->thread));
    }

    /**
     * Count the thread's replies at every level, and its top-level comments.
     *
     * @return array{replies_count: int, comments_count: int}
     */
    private function totals(ForumThread $thread): array
    {
        return [
            'replies_count' => $thread->replies()->count(),
            'comments_count' => $thread->comments()->reorder()->count(),
        ];
    }
}
