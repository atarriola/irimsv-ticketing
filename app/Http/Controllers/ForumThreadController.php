<?php

namespace App\Http\Controllers;

use App\Enums\ForumThreadType;
use App\Enums\ReactionType;
use App\Http\Requests\StoreForumThreadRequest;
use App\Http\Requests\UpdateForumThreadRequest;
use App\Http\Resources\ForumReplyResource;
use App\Http\Resources\ForumThreadResource;
use App\Models\ForumReply;
use App\Models\ForumThread;
use App\Models\ForumTopic;
use App\Models\User;
use App\Notifications\ForumThreadStarted;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ForumThreadController extends Controller
{
    /**
     * Display the forum feed, optionally narrowed to one topic.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', ForumThread::class);

        $options = $this->formOptions();
        $topic = $options['topics']->firstWhere('slug', $request->query('topic'));
        $threads = fn () => ForumThread::query()->when($topic, fn ($query) => $query->whereBelongsTo($topic, 'topic'));

        return Inertia::render('Forum/Index', [
            ...$options,
            'currentTopic' => $topic?->slug,
            'reactionTypes' => ReactionType::options(),
            'threads' => Inertia::scroll(fn () => $threads()
                ->with([
                    'author:'.User::DISPLAY_COLUMNS,
                    'topic:id,name,slug',
                    'reactions',
                    ...array_map(fn (string $relation): string => "previewComments.{$relation}", ForumThread::COMMENT_RELATIONS),
                ])
                ->withCount(['replies', 'comments'])
                ->mostRecentlyActive()
                ->paginate(15)
                ->withQueryString()
                ->through(fn (ForumThread $thread): array => ForumThreadResource::make($thread)->resolve($request))),
            // The newest thread in the feed, and how many were posted after the one an open page has "seen"; the page polls the latter.
            'latestThreadId' => $threads()->max('id'),
            'newThreadsCount' => Inertia::optional(fn (): int => $request->has('seen')
                ? $threads()->where('id', '>', $request->integer('seen'))->count()
                : 0),
            // Per thread on screen, the reply count and version, so a feed without a WebSocket can tell which threads to resync.
            'threadActivity' => Inertia::optional(fn (): object => (object) $threads()
                ->whereIn('id', array_slice(array_map(intval(...), (array) $request->input('threads', [])), 0, 50))
                ->withCount('replies')
                ->get(['id', 'updated_at', 'last_activity_at'])
                ->mapWithKeys(fn (ForumThread $thread): array => [$thread->id => [
                    'replies_count' => $thread->replies_count,
                    'version' => max($thread->updated_at, $thread->last_activity_at)->timestamp,
                ]])
                ->all()),
            'can' => [
                'create' => $request->user()->can('create', ForumThread::class),
            ],
        ]);
    }

    /**
     * Start a new thread as the signed-in user.
     */
    public function store(StoreForumThreadRequest $request): RedirectResponse
    {
        $thread = $request->user()->forumThreads()->create($request->validated());

        $this->notifyAdministrators($thread, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Your thread has been posted.']);

        return redirect()->route('forum.index');
    }

    /**
     * Tell the helpdesk administrators about the new thread, except the one who started it.
     *
     * A failure to notify one of them is reported and never stops the thread from being posted.
     */
    private function notifyAdministrators(ForumThread $thread, User $author): void
    {
        $thread->setRelation('author', $author);

        User::administrators()
            ->whereKeyNot($author->getKey())
            ->get()
            ->each(fn (User $administrator) => rescue(fn () => $administrator->notify(new ForumThreadStarted($thread))));
    }

    /**
     * Display a thread with its replies.
     */
    public function show(Request $request, ForumThread $thread): Response
    {
        Gate::authorize('view', $thread);

        // Taken before the conversation is read, so the page's first live check picks up any edit made from this moment on.
        $syncedAt = now();

        $thread->load(['topic:id,name,slug', 'author:'.User::DISPLAY_COLUMNS, 'reactions'])->loadCount(['replies', 'comments']);

        $comments = $thread->comments()
            ->with(ForumThread::COMMENT_RELATIONS)
            ->paginate(ForumThread::COMMENTS_PER_PAGE);

        return Inertia::render('Forum/Thread', [
            'thread' => ForumThreadResource::make($thread)->resolve($request),
            'comments' => $comments->getCollection()->map(fn (ForumReply $comment): array => ForumReplyResource::make($comment)->resolve($request)),
            'nextCommentsPage' => $comments->hasMorePages() ? 2 : null,
            'syncedAt' => $syncedAt->toIso8601String(),
            'reactionTypes' => ReactionType::options(),
            'can' => [
                'moderate' => $request->user()->can('moderate', $thread),
                'update' => $request->user()->can('update', $thread),
                'delete' => $request->user()->can('delete', $thread),
            ],
        ]);
    }

    /**
     * Display the form for editing a thread.
     */
    public function edit(ForumThread $thread): Response
    {
        Gate::authorize('update', $thread);

        return Inertia::render('Forum/EditThread', [
            ...$this->formOptions(),
            'thread' => [
                'id' => $thread->id,
                'forum_topic_id' => $thread->forum_topic_id,
                'type' => $thread->type->value,
                'body' => $thread->body,
            ],
        ]);
    }

    /**
     * Update a thread.
     */
    public function update(UpdateForumThreadRequest $request, ForumThread $thread): RedirectResponse
    {
        $thread->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'The thread has been updated.']);

        return redirect()->route('forum.threads.show', $thread);
    }

    /**
     * Delete a thread together with its replies.
     */
    public function destroy(ForumThread $thread): RedirectResponse
    {
        Gate::authorize('delete', $thread);

        $thread->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'The thread has been deleted.']);

        return redirect()->route('forum.index');
    }

    /**
     * Get the choices offered when posting or editing a thread.
     *
     * @return array{topics: Collection<int, ForumTopic>, types: list<array{value: string, label: string}>}
     */
    private function formOptions(): array
    {
        return [
            'topics' => ForumTopic::orderBy('position')->orderBy('name')->get(['id', 'name', 'slug']),
            'types' => array_map(fn (ForumThreadType $type): array => ['value' => $type->value, 'label' => $type->label()], ForumThreadType::cases()),
        ];
    }
}
