<?php

namespace App\Http\Controllers;

use App\Enums\NewsKind;
use App\Enums\TicketEventKind;
use App\Enums\TicketGroup;
use App\Enums\TicketPriority;
use App\Enums\TicketSort;
use App\Enums\TicketStatus;
use App\Enums\TicketType;
use App\Enums\WaitingOn;
use App\Http\Requests\StoreTicketRequest;
use App\Http\Requests\TicketListRequest;
use App\Http\Requests\UpdateTicketRequest;
use App\Http\Resources\TicketAttachmentResource;
use App\Http\Resources\TicketCommentResource;
use App\Http\Resources\TicketEventResource;
use App\Http\Resources\TicketResource;
use App\Models\Category;
use App\Models\ForumThread;
use App\Models\NewsPost;
use App\Models\SavedReply;
use App\Models\Ticket;
use App\Models\TicketAttachment;
use App\Models\TicketComment;
use App\Models\TicketEvent;
use App\Models\User;
use App\Notifications\TicketRaised;
use BackedEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class TicketController extends Controller
{
    /**
     * The most cards shown in one board column.
     */
    private const int CARDS_PER_COLUMN = 50;

    /**
     * Display the tickets of one group as a board or a list.
     */
    public function index(TicketListRequest $request): Response
    {
        $user = $request->user();
        $group = $request->group();
        $view = $request->view();

        // A board shows every status as a column, so a status filter only applies to the list.
        $tickets = Ticket::query()
            ->visibleTo($user)
            ->filtered($request->filters(withStatus: $view === 'list'), $user)
            ->with(['requester:'.User::DISPLAY_COLUMNS, 'category:id,name'])
            ->withCount(['supporters', 'comments' => fn (Builder $query) => $query->visibleTo($user)]);

        return Inertia::render('Tickets/Index', [
            'view' => $view,
            'group' => $group->value,
            'groups' => $this->groupCounts($user),
            'filters' => $request->filtersForPage(),
            'statuses' => $this->options(TicketStatus::forGroup($group)),
            'priorities' => $this->options(array_reverse(TicketPriority::cases())),
            'categories' => Category::orderBy('name')->get(['id', 'name']),
            'waitingOptions' => $this->options(WaitingOn::cases()),
            'sorts' => $this->options(TicketSort::cases()),
            'columns' => $view === 'board' ? $this->boardColumns($tickets, $group, $request) : null,
            'tickets' => $view === 'list'
                ? $tickets
                    ->sortedBy($request->sort())
                    ->paginate(20)
                    ->withQueryString()
                    ->through(fn (Ticket $ticket): array => TicketResource::make($ticket)->resolve($request))
                : null,
            'can' => [
                'moveCards' => $user->isAdmin(),
                'bulkUpdate' => $user->isAdmin(),
                'export' => $user->can('export', Ticket::class),
                'viewTrashed' => $user->isAdmin(),
            ],
        ]);
    }

    /**
     * Build one board column per status of the group with its most urgent, most recently active tickets.
     *
     * @param  Builder<Ticket>  $tickets
     * @return list<array{status: string, label: string, total: int, tickets: list<array<string, mixed>>}>
     */
    private function boardColumns(Builder $tickets, TicketGroup $group, Request $request): array
    {
        $totals = (clone $tickets)->toBase()
            ->reorder()
            ->select('status')
            ->selectRaw('count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return array_map(fn (TicketStatus $status): array => [
            'status' => $status->value,
            'label' => $status->label(),
            'total' => (int) ($totals[$status->value] ?? 0),
            'tickets' => (clone $tickets)
                ->where('status', $status)
                ->mostUrgentFirst()
                ->mostRecentlyActive()
                ->limit(self::CARDS_PER_COLUMN)
                ->get()
                ->map(fn (Ticket $ticket): array => TicketResource::make($ticket)->resolve($request))
                ->all(),
        ], TicketStatus::forGroup($group));
    }

    /**
     * Display the form for raising a ticket, prefilled from a forum thread when raised out of one.
     */
    public function create(Request $request): Response
    {
        Gate::authorize('create', Ticket::class);

        $thread = $request->integer('thread') > 0 ? ForumThread::find($request->integer('thread')) : null;

        if ($thread !== null && ! $request->user()->can('raiseTicket', $thread)) {
            $thread = null;
        }

        return Inertia::render('Tickets/Create', [
            ...$this->formOptions(),
            'defaultType' => ($request->enum('type', TicketType::class) ?? TicketType::BugReport)->value,
            'canSetPriority' => true,
            'thread' => $thread === null ? null : [
                'id' => $thread->id,
                'excerpt' => $thread->excerpt,
                'body' => $thread->body,
            ],
            'maintenanceNotice' => $this->maintenanceNotice(),
        ]);
    }

    /**
     * Raise a new ticket for the signed-in user and tell the helpdesk about it.
     */
    public function store(StoreTicketRequest $request): RedirectResponse
    {
        $user = $request->user();
        $ticket = $user->tickets()->create($request->ticketAttributes());

        $this->attachImages($ticket, $request);
        $ticket->recordEvent(TicketEventKind::Created, $user);
        $this->notifyAdministrators($ticket, $user);

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$ticket->key} has been created."]);

        return redirect()->route('tickets.show', $ticket);
    }

    /**
     * Tell the helpdesk administrators about the new ticket, except the one who raised it.
     *
     * A failure to notify one of them is reported and never stops the ticket from being raised.
     */
    private function notifyAdministrators(Ticket $ticket, User $requester): void
    {
        $ticket->setRelation('requester', $requester);

        User::administrators()
            ->whereKeyNot($requester->getKey())
            ->get()
            ->each(fn (User $administrator) => rescue(fn () => $administrator->notify(new TicketRaised($ticket))));
    }

    /**
     * Display a ticket with its conversation and timeline.
     */
    public function show(Request $request, Ticket $ticket): Response
    {
        Gate::authorize('view', $ticket);

        $user = $request->user();
        $ticket->load(['requester:'.User::DISPLAY_COLUMNS.',email', 'category:id,name', 'screenshots', 'newsPost:id,title', 'forumThread:id,body'])
            ->loadCount('supporters');

        $comments = $ticket->comments()
            ->visibleTo($user)
            ->with(['author:'.User::DISPLAY_COLUMNS, 'attachments'])
            ->oldest()
            ->oldest('id')
            ->get()
            ->each(fn (TicketComment $comment) => $comment->setRelation('ticket', $ticket))
            ->map(fn (TicketComment $comment): array => TicketCommentResource::make($comment)->resolve($request));

        $timeline = $ticket->events()
            ->with('user:'.User::DISPLAY_COLUMNS)
            ->get()
            ->map(fn (TicketEvent $event): array => TicketEventResource::make($event)->resolve($request));

        $following = $ticket->watchers()->whereKey($user->getKey())->first()?->pivot;

        return Inertia::render('Tickets/Show', [
            'ticket' => [
                'id' => $ticket->id,
                'key' => $ticket->key,
                'subject' => $ticket->subject,
                'description' => $ticket->description,
                'type' => $ticket->type->label(),
                'type_key' => $ticket->type->value,
                'group' => TicketGroup::forType($ticket->type)->value,
                'status' => $ticket->status->value,
                'status_label' => $ticket->status->label(),
                'priority' => $ticket->priority->value,
                'waiting_on' => $ticket->waiting_on?->value,
                'waiting_label' => $ticket->waiting_on?->label(),
                'is_shared' => $ticket->is_shared,
                'is_deleted' => $ticket->trashed(),
                'is_mine' => $ticket->user_id === $user->id,
                'awaiting_confirmation' => $ticket->isAwaitingConfirmation(),
                'rating' => $ticket->rating,
                'rating_comment' => $ticket->rating_comment,
                'supporters_count' => $ticket->supporters_count,
                'is_watching' => $following !== null,
                'is_affected' => (bool) ($following?->is_affected ?? false),
                'category' => $ticket->category?->name,
                'requester' => $ticket->requester->only(['name', 'position', 'email', 'photo_url']),
                'release_post' => $ticket->newsPost === null ? null : [
                    'id' => $ticket->newsPost->id,
                    'title' => $ticket->newsPost->title,
                    'url' => route('news.show', $ticket->newsPost),
                ],
                'forum_thread' => $ticket->forumThread === null ? null : [
                    'id' => $ticket->forumThread->id,
                    'excerpt' => $ticket->forumThread->excerpt,
                    'url' => route('forum.threads.show', $ticket->forumThread),
                ],
                'created_at' => $ticket->created_at->toDayDateTimeString(),
                'updated_at' => $ticket->last_activity_at?->diffForHumans() ?? $ticket->updated_at->diffForHumans(),
                'resolved_at' => $ticket->resolved_at?->toDayDateTimeString(),
                'closed_at' => $ticket->closed_at?->toDayDateTimeString(),
                'deleted_at' => $ticket->deleted_at?->toDayDateTimeString(),
                'attachments' => $this->attachmentsFor($ticket, $request),
            ],
            'comments' => $comments,
            'timeline' => $timeline,
            'statuses' => $this->options(TicketStatus::forType($ticket->type)),
            'priorities' => $this->options(TicketPriority::cases()),
            'releasePosts' => $user->can('linkRelease', $ticket)
                ? NewsPost::published()->where('kind', NewsKind::Release)->newestFirst()->get(['id', 'title'])
                : [],
            'savedReplies' => $user->can('viewAny', SavedReply::class)
                ? SavedReply::orderBy('title')->get(['id', 'title', 'body'])
                : [],
            'can' => [
                'update' => $user->can('update', $ticket),
                'delete' => $user->can('delete', $ticket),
                'restore' => $user->can('restore', $ticket),
                'changeStatus' => $user->can('changeStatus', $ticket),
                'changePriority' => $user->can('changePriority', $ticket),
                'confirmResolution' => $user->can('confirmResolution', $ticket),
                'comment' => $user->can('comment', $ticket),
                'addInternalNote' => $user->can('addInternalNote', $ticket),
                'watch' => $user->can('watch', $ticket),
                'rate' => $user->can('rate', $ticket),
                'linkRelease' => $user->can('linkRelease', $ticket),
            ],
        ]);
    }

    /**
     * Display the form for editing a ticket's details.
     */
    public function edit(Request $request, Ticket $ticket): Response
    {
        Gate::authorize('update', $ticket);

        $ticket->load('screenshots');

        return Inertia::render('Tickets/Edit', [
            ...$this->formOptions(),
            'canSetPriority' => $request->user()->can('changePriority', $ticket),
            'ticket' => [
                'id' => $ticket->id,
                'key' => $ticket->key,
                'type' => $ticket->type->value,
                'priority' => $ticket->priority->value,
                'category_id' => $ticket->category_id,
                'subject' => $ticket->subject,
                'description' => $ticket->description,
                'is_shared' => $ticket->is_shared,
                'attachments' => $this->attachmentsFor($ticket, $request),
            ],
        ]);
    }

    /**
     * Update a ticket's details, adding any images sent along, and note what changed.
     */
    public function update(UpdateTicketRequest $request, Ticket $ticket): RedirectResponse
    {
        $user = $request->user();
        $attributes = $request->ticketAttributes();
        $priority = Arr::pull($attributes, 'priority');

        $ticket->fill($attributes);
        $changed = array_keys($ticket->getDirty());

        if ($changed !== []) {
            $ticket->last_activity_at = now();
            $ticket->save();
            $ticket->recordEvent(TicketEventKind::Edited, $user, ['fields' => $changed]);
        }

        if ($priority !== null) {
            $ticket->changePriority(TicketPriority::from($priority), $user);
        }

        $this->attachImages($ticket, $request);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'The ticket has been updated.']);

        return redirect()->route('tickets.show', $ticket);
    }

    /**
     * Delete a ticket. It keeps its conversation and can be restored by an administrator for a while.
     */
    public function destroy(Request $request, Ticket $ticket): RedirectResponse
    {
        Gate::authorize('delete', $ticket);

        $group = TicketGroup::forType($ticket->type);
        $ticket->recordEvent(TicketEventKind::Deleted, $request->user());
        $ticket->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$ticket->key} has been deleted. It can be restored for ".Ticket::DAYS_KEPT_AFTER_DELETION.' days.']);

        return redirect()->route('tickets.index', ['group' => $group->value]);
    }

    /**
     * Keep the images sent with the form on the ticket.
     */
    private function attachImages(Ticket $ticket, StoreTicketRequest $request): void
    {
        foreach ($request->validated('attachments') ?? [] as $file) {
            $ticket->addAttachment($file, $request->user());
        }
    }

    /**
     * Describe the ticket's own images for the client.
     *
     * @return list<array<string, mixed>>
     */
    private function attachmentsFor(Ticket $ticket, Request $request): array
    {
        return $ticket->screenshots
            ->map(fn (TicketAttachment $attachment): array => TicketAttachmentResource::make($attachment)->resolve($request))
            ->all();
    }

    /**
     * Get the choices offered by the ticket form.
     *
     * @return array{types: list<array{value: string, label: string}>, priorities: list<array{value: string, label: string}>, categories: mixed}
     */
    private function formOptions(): array
    {
        return [
            'types' => $this->options(TicketType::cases()),
            'priorities' => $this->options(TicketPriority::cases()),
            'categories' => Category::orderBy('name')->get(['id', 'name']),
        ];
    }

    /**
     * Get the maintenance notice to show above the form, if one went out recently.
     *
     * @return array{id: int, title: string, excerpt: string, url: string}|null
     */
    private function maintenanceNotice(): ?array
    {
        $post = NewsPost::published()
            ->where('kind', NewsKind::Maintenance)
            ->where('published_at', '>=', now()->subDays((int) config('helpdesk.maintenance_notice_days')))
            ->newestFirst()
            ->first(['id', 'title', 'body', 'published_at']);

        return $post === null ? null : [
            'id' => $post->id,
            'title' => $post->title,
            'excerpt' => $post->excerpt,
            'url' => route('news.show', $post),
        ];
    }

    /**
     * Turn enum cases into value and label pairs for the client.
     *
     * @param  list<BackedEnum>  $cases
     * @return list<array{value: string, label: string}>
     */
    private function options(array $cases): array
    {
        return array_map(fn (BackedEnum $case): array => ['value' => $case->value, 'label' => $case->label()], $cases);
    }

    /**
     * Count the active tickets the user may see in each group.
     *
     * @return list<array{key: string, label: string, count: int}>
     */
    private function groupCounts(User $user): array
    {
        $totals = Ticket::query()
            ->visibleTo($user)
            ->active()
            ->toBase()
            ->select('type')
            ->selectRaw('count(*) as total')
            ->groupBy('type')
            ->pluck('total', 'type');

        return array_map(fn (TicketGroup $group): array => [
            'key' => $group->value,
            'label' => $group->label(),
            'count' => (int) collect($group->types())->sum(fn (TicketType $type) => $totals[$type->value] ?? 0),
        ], TicketGroup::cases());
    }
}
