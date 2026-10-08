<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateForumThreadAnswerRequest;
use App\Models\ForumThread;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class ForumThreadAnswerController extends Controller
{
    /**
     * Mark a comment as the thread's answer, or clear the mark.
     */
    public function update(UpdateForumThreadAnswerRequest $request, ForumThread $thread): RedirectResponse
    {
        $replyId = $request->filled('reply_id') ? $request->integer('reply_id') : null;

        $thread->forceFill(['accepted_reply_id' => $replyId])->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => $replyId === null
            ? 'The answer mark has been removed.'
            : 'The comment is now marked as the answer.']);

        return back();
    }
}
