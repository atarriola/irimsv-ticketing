<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateForumThreadModerationRequest;
use App\Models\ForumThread;
use Illuminate\Http\RedirectResponse;

class ForumThreadModerationController extends Controller
{
    /**
     * Pin, unpin, lock or unlock a thread.
     */
    public function update(UpdateForumThreadModerationRequest $request, ForumThread $thread): RedirectResponse
    {
        $thread->forceFill($request->safe()->only(['is_pinned', 'is_locked']))->save();

        return back();
    }
}
