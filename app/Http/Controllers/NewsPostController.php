<?php

namespace App\Http\Controllers;

use App\Enums\NewsKind;
use App\Http\Requests\SaveNewsPostRequest;
use App\Http\Resources\NewsAttachmentResource;
use App\Http\Resources\NewsPostResource;
use App\Models\NewsPost;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class NewsPostController extends Controller
{
    /**
     * Display the news, newest first, optionally only one kind of it.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', NewsPost::class);

        $kind = $request->enum('kind', NewsKind::class);

        return Inertia::render('News/Index', [
            'kinds' => NewsKind::options(),
            'currentKind' => $kind?->value,
            'posts' => NewsPost::visibleTo($request->user())
                ->when($kind, fn (Builder $query) => $query->where('kind', $kind))
                ->with(['author:'.User::DISPLAY_COLUMNS, 'coverImage'])
                ->newestFirst()
                ->paginate(10)
                ->withQueryString()
                ->through(fn (NewsPost $post): array => NewsPostResource::make($post)->resolve($request)),
            'can' => [
                'create' => $request->user()->can('create', NewsPost::class),
            ],
        ]);
    }

    /**
     * Display the form for writing a post.
     */
    public function create(): Response
    {
        Gate::authorize('create', NewsPost::class);

        return Inertia::render('News/Form', ['kinds' => NewsKind::options(), 'post' => null]);
    }

    /**
     * Publish a new post by the signed-in administrator, or keep it as a draft, with any photos and videos sent along.
     */
    public function store(SaveNewsPostRequest $request): RedirectResponse
    {
        $post = $request->user()->newsPosts()->create($request->postAttributes());

        $this->attachFiles($post, $request);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $post->isPublished() ? 'The post has been published.' : 'The draft has been saved.',
        ]);

        return redirect()->route('news.show', $post);
    }

    /**
     * Display a post.
     */
    public function show(Request $request, NewsPost $post): Response
    {
        Gate::authorize('view', $post);

        $post->load(['author:'.User::DISPLAY_COLUMNS, 'attachments']);

        return Inertia::render('News/Show', [
            'post' => NewsPostResource::make($post)->resolve($request),
            'can' => [
                'update' => $request->user()->can('update', $post),
                'delete' => $request->user()->can('delete', $post),
            ],
        ]);
    }

    /**
     * Display the form for editing a post.
     */
    public function edit(Request $request, NewsPost $post): Response
    {
        Gate::authorize('update', $post);

        $post->load('attachments');

        return Inertia::render('News/Form', [
            'kinds' => NewsKind::options(),
            'post' => [
                'id' => $post->id,
                'kind' => $post->kind->value,
                'title' => $post->title,
                'body' => $post->body,
                'is_published' => $post->isPublished(),
                'attachments' => NewsAttachmentResource::collection($post->attachments)->resolve($request),
            ],
        ]);
    }

    /**
     * Update a post, adding any photos and videos sent along.
     */
    public function update(SaveNewsPostRequest $request, NewsPost $post): RedirectResponse
    {
        $post->update($request->postAttributes());

        $this->attachFiles($post, $request);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'The post has been updated.']);

        return redirect()->route('news.show', $post);
    }

    /**
     * Delete a post together with its files.
     */
    public function destroy(NewsPost $post): RedirectResponse
    {
        Gate::authorize('delete', $post);

        $post->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'The post has been deleted.']);

        return redirect()->route('news.index');
    }

    /**
     * Keep the photos and videos sent with the form on the post.
     */
    private function attachFiles(NewsPost $post, SaveNewsPostRequest $request): void
    {
        foreach ($request->validated('attachments') ?? [] as $file) {
            $post->addAttachment($file);
        }
    }
}
