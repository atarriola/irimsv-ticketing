<?php

namespace App\Http\Controllers;

use App\Enums\ReactionType;
use App\Http\Requests\StoreForumReactionRequest;
use App\Models\ForumReaction;
use App\Models\ForumReply;
use App\Notifications\ForumCommentReacted;
use Illuminate\Http\JsonResponse;

class ForumReplyReactionController extends Controller
{
    /**
     * Toggle the user's reaction to a reply.
     */
    public function store(StoreForumReactionRequest $request, ForumReply $reply): JsonResponse
    {
        $reaction = $reply->toggleReaction($request->user(), $request->enum('type', ReactionType::class));

        if ($reaction?->wasRecentlyCreated) {
            $this->notifyAuthor($reply, $reaction->setRelation('user', $request->user()));
        }

        return response()->json($reply->load('reactions')->reactionSummary($request->user()));
    }

    /**
     * Tell the reply's author about a new reaction, unless it is their own.
     *
     * A failure to notify them is reported without undoing the reaction.
     */
    private function notifyAuthor(ForumReply $reply, ForumReaction $reaction): void
    {
        $reply->loadMissing(['thread', 'author']);

        if ($reply->author->isNot($reaction->user)) {
            rescue(fn () => $reply->author->notify(new ForumCommentReacted($reply->thread, $reply, $reaction)));
        }
    }
}
