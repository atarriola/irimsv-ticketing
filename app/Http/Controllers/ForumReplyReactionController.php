<?php

namespace App\Http\Controllers;

use App\Enums\ReactionType;
use App\Http\Requests\StoreForumReactionRequest;
use App\Models\ForumReply;
use Illuminate\Http\JsonResponse;

class ForumReplyReactionController extends Controller
{
    /**
     * Toggle the user's reaction to a reply.
     */
    public function store(StoreForumReactionRequest $request, ForumReply $reply): JsonResponse
    {
        $reply->toggleReaction($request->user(), $request->enum('type', ReactionType::class));

        return response()->json($reply->load('reactions')->reactionSummary($request->user()));
    }
}
