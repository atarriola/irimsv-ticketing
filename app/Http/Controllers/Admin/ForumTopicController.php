<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveForumTopicRequest;
use App\Models\ForumTopic;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ForumTopicController extends Controller
{
    /**
     * The wording and addresses the shared admin pages need for forum topics.
     *
     * @var array{singular: string, plural: string, baseUrl: string, description: string, countLabel: string, hasPosition: bool, deleteWarning: string}
     */
    private const array RESOURCE = [
        'singular' => 'topic',
        'plural' => 'Forum topics',
        'baseUrl' => '/admin/forum-topics',
        'description' => 'Topics are optional labels a forum thread can be filed under. Without any, threads are simply posted to the feed.',
        'countLabel' => 'threads',
        'hasPosition' => true,
        'deleteWarning' => 'A topic can only be deleted once it has no threads.',
    ];

    /**
     * Display the forum topics.
     */
    public function index(): Response
    {
        Gate::authorize('viewAny', ForumTopic::class);

        $items = ForumTopic::withCount('threads')
            ->orderBy('position')
            ->orderBy('name')
            ->get()
            ->map(fn (ForumTopic $topic): array => [
                'key' => $topic->slug,
                'name' => $topic->name,
                'description' => $topic->description,
                'position' => $topic->position,
                'count' => $topic->threads_count,
            ]);

        return Inertia::render('Admin/Taxonomy/Index', ['resource' => self::RESOURCE, 'items' => $items]);
    }

    /**
     * Display the form for creating a topic.
     */
    public function create(): Response
    {
        Gate::authorize('create', ForumTopic::class);

        return Inertia::render('Admin/Taxonomy/Form', ['resource' => self::RESOURCE, 'item' => null]);
    }

    /**
     * Create a topic.
     */
    public function store(SaveForumTopicRequest $request): RedirectResponse
    {
        $topic = ForumTopic::create($request->topicAttributes());

        Inertia::flash('toast', ['type' => 'success', 'message' => "The {$topic->name} topic has been created."]);

        return redirect()->route('admin.forum-topics.index');
    }

    /**
     * Display the form for editing a topic.
     */
    public function edit(ForumTopic $topic): Response
    {
        Gate::authorize('update', $topic);

        return Inertia::render('Admin/Taxonomy/Form', [
            'resource' => self::RESOURCE,
            'item' => [
                'key' => $topic->slug,
                'name' => $topic->name,
                'description' => $topic->description,
                'position' => $topic->position,
            ],
        ]);
    }

    /**
     * Update a topic.
     */
    public function update(SaveForumTopicRequest $request, ForumTopic $topic): RedirectResponse
    {
        $topic->update($request->topicAttributes());

        Inertia::flash('toast', ['type' => 'success', 'message' => "The {$topic->name} topic has been updated."]);

        return redirect()->route('admin.forum-topics.index');
    }

    /**
     * Delete a topic, unless threads still live in it.
     */
    public function destroy(ForumTopic $topic): RedirectResponse
    {
        Gate::authorize('delete', $topic);

        if ($topic->threads()->exists()) {
            Inertia::flash('toast', ['type' => 'error', 'message' => "The {$topic->name} topic still has threads. Move or delete them first."]);

            return redirect()->route('admin.forum-topics.index');
        }

        $topic->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => "The {$topic->name} topic has been deleted."]);

        return redirect()->route('admin.forum-topics.index');
    }
}
