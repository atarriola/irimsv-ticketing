<?php

namespace App\Http\Controllers;

use App\Enums\ReactionType;
use App\Http\Requests\StoreForumReactionRequest;
use App\Models\ForumThread;
use Illuminate\Http\JsonResponse;

class ForumThreadReactionController extends Controller
{
    /**
     * Toggle the user's reaction to a thread.
     */
    public function store(StoreForumReactionRequest $request, ForumThread $thread): JsonResponse
    {
        $thread->toggleReaction($request->user(), $request->enum('type', ReactionType::class));

        return response()->json($thread->load('reactions')->reactionSummary($request->user()));
    }
}
