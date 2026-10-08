<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveSavedReplyRequest;
use App\Models\SavedReply;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class SavedReplyController extends Controller
{
    /**
     * Display the saved replies.
     */
    public function index(): Response
    {
        Gate::authorize('viewAny', SavedReply::class);

        $replies = SavedReply::query()
            ->with('author:'.User::DISPLAY_COLUMNS)
            ->orderBy('title')
            ->get()
            ->map(fn (SavedReply $reply): array => [
                'id' => $reply->id,
                'title' => $reply->title,
                'excerpt' => str($reply->body)->squish()->limit(120)->toString(),
                'author' => $reply->author?->name,
                'updated_at' => $reply->updated_at->diffForHumans(),
            ]);

        return Inertia::render('Admin/SavedReplies/Index', ['replies' => $replies]);
    }

    /**
     * Display the form for writing a saved reply.
     */
    public function create(): Response
    {
        Gate::authorize('create', SavedReply::class);

        return Inertia::render('Admin/SavedReplies/Form', ['reply' => null]);
    }

    /**
     * Keep a new saved reply.
     */
    public function store(SaveSavedReplyRequest $request): RedirectResponse
    {
        $reply = $request->user()->savedReplies()->create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => "The saved reply \"{$reply->title}\" has been created."]);

        return redirect()->route('admin.saved-replies.index');
    }

    /**
     * Display the form for editing a saved reply.
     */
    public function edit(SavedReply $savedReply): Response
    {
        Gate::authorize('update', $savedReply);

        return Inertia::render('Admin/SavedReplies/Form', [
            'reply' => $savedReply->only(['id', 'title', 'body']),
        ]);
    }

    /**
     * Update a saved reply.
     */
    public function update(SaveSavedReplyRequest $request, SavedReply $savedReply): RedirectResponse
    {
        $savedReply->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => "The saved reply \"{$savedReply->title}\" has been updated."]);

        return redirect()->route('admin.saved-replies.index');
    }

    /**
     * Delete a saved reply.
     */
    public function destroy(SavedReply $savedReply): RedirectResponse
    {
        Gate::authorize('delete', $savedReply);

        $savedReply->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => "The saved reply \"{$savedReply->title}\" has been deleted."]);

        return redirect()->route('admin.saved-replies.index');
    }
}
