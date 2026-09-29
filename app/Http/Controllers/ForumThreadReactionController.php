<?php

namespace App\Http\Controllers;

use App\Enums\ReactionType;
use App\Http\Requests\StoreForumReactionRequest;
use App\Models\ForumReaction;
use App\Models\ForumThread;
use App\Notifications\ForumThreadReacted;
use Illuminate\Http\JsonResponse;

class ForumThreadReactionController extends Controller
{
    /**
     * Toggle the user's reaction to a thread.
     */
    public function store(StoreForumReactionRequest $request, ForumThread $thread): JsonResponse
    {
        $reaction = $thread->toggleReaction($request->user(), $request->enum('type', ReactionType::class));

        if ($reaction?->wasRecentlyCreated) {
            $this->notifyAuthor($thread, $reaction->setRelation('user', $request->user()));
        }

        return response()->json($thread->load('reactions')->reactionSummary($request->user()));
    }

    /**
     * Tell the thread's author about a new reaction, unless it is their own.
     *
     * A failure to notify them is reported without undoing the reaction.
     */
    private function notifyAuthor(ForumThread $thread, ForumReaction $reaction): void
    {
        $thread->loadMissing('author');

        if ($thread->author->isNot($reaction->user)) {
            rescue(fn () => $thread->author->notify(new ForumThreadReacted($thread, $reaction)));
        }
    }
}
