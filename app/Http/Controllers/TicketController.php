<?php

namespace App\Http\Controllers;

use App\Enums\TicketGroup;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Enums\TicketType;
use App\Http\Requests\StoreTicketRequest;
use App\Http\Requests\UpdateTicketRequest;
use App\Http\Resources\TicketCommentResource;
use App\Http\Resources\TicketResource;
use App\Models\Category;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\User;
use BackedEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
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
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Ticket::class);

        $user = $request->user();
        $group = $request->enum('group', TicketGroup::class) ?? TicketGroup::Issues;
        $status = $request->enum('status', TicketStatus::class);
        $priority = $request->enum('priority', TicketPriority::class);
        $search = Str::limit(trim((string) $request->string('q')), 100, '');
        $view = $request->query('view') === 'list' ? 'list' : 'board';

        $tickets = Ticket::visibleTo($user)
            ->whereIn('type', $group->types())
            ->when($priority, fn (Builder $query) => $query->where('priority', $priority))
            ->when($search !== '', fn (Builder $query) => $query->search($search))
            ->with(['requester:id,name', 'category:id,name'])
            ->withCount('comments');

        return Inertia::render('Tickets/Index', [
            'view' => $view,
            'group' => $group->value,
            'groups' => $this->groupCounts($user),
            'filters' => [
                'q' => $search,
                'status' => $status?->value,
                'priority' => $priority?->value,
            ],
            'statuses' => $this->options(TicketStatus::cases()),
            'priorities' => $this->options(array_reverse(TicketPriority::cases())),
            'columns' => $view === 'board' ? $this->boardColumns($tickets, $request) : null,
            'tickets' => $view === 'list'
                ? $tickets
                    ->when($status, fn (Builder $query) => $query->where('status', $status))
                    ->latest()
                    ->latest('id')
                    ->paginate(20)
                    ->withQueryString()
                    ->through(fn (Ticket $ticket): array => TicketResource::make($ticket)->resolve($request))
                : null,
            'can' => [
                'moveCards' => $user->isAdmin(),
            ],
        ]);
    }

    /**
     * Build one board column per status with its most urgent tickets.
     *
     * @param  Builder<Ticket>  $tickets
     * @return list<array{status: string, label: string, total: int, tickets: list<array<string, mixed>>}>
     */
    private function boardColumns(Builder $tickets, Request $request): array
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
                ->latest('updated_at')
                ->latest('id')
                ->limit(self::CARDS_PER_COLUMN)
                ->get()
                ->map(fn (Ticket $ticket): array => TicketResource::make($ticket)->resolve($request))
                ->all(),
        ], TicketStatus::cases());
    }

    /**
     * Display the form for raising a ticket.
     */
    public function create(Request $request): Response
    {
        Gate::authorize('create', Ticket::class);

        return Inertia::render('Tickets/Create', [
            ...$this->formOptions(),
            'defaultType' => ($request->enum('type', TicketType::class) ?? TicketType::BugReport)->value,
        ]);
    }

    /**
     * Raise a new ticket for the signed-in user.
     */
    public function store(StoreTicketRequest $request): RedirectResponse
    {
        $ticket = $request->user()->tickets()->create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$ticket->key} has been created."]);

        return redirect()->route('tickets.show', $ticket);
    }

    /**
     * Display a ticket with its conversation.
     */
    public function show(Request $request, Ticket $ticket): Response
    {
        Gate::authorize('view', $ticket);

        $user = $request->user();
        $ticket->load(['requester:id,name,email', 'category:id,name']);

        $comments = $ticket->comments()
            ->with('author:id,name,role')
            ->oldest()
            ->oldest('id')
            ->get()
            ->map(fn (TicketComment $comment): array => TicketCommentResource::make($comment)->resolve($request));

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
                'priority' => $ticket->priority->value,
                'category' => $ticket->category?->name,
                'requester' => $ticket->requester->only(['name', 'email']),
                'created_at' => $ticket->created_at->toDayDateTimeString(),
                'updated_at' => $ticket->updated_at->diffForHumans(),
                'resolved_at' => $ticket->resolved_at?->toDayDateTimeString(),
                'closed_at' => $ticket->closed_at?->toDayDateTimeString(),
            ],
            'comments' => $comments,
            'statuses' => $this->options(TicketStatus::cases()),
            'can' => [
                'update' => $user->can('update', $ticket),
                'delete' => $user->can('delete', $ticket),
                'changeStatus' => $user->can('changeStatus', $ticket),
                'comment' => $user->can('comment', $ticket),
            ],
        ]);
    }

    /**
     * Display the form for editing a ticket's details.
     */
    public function edit(Ticket $ticket): Response
    {
        Gate::authorize('update', $ticket);

        return Inertia::render('Tickets/Edit', [
            ...$this->formOptions(),
            'ticket' => [
                'id' => $ticket->id,
                'key' => $ticket->key,
                'type' => $ticket->type->value,
                'priority' => $ticket->priority->value,
                'category_id' => $ticket->category_id,
                'subject' => $ticket->subject,
                'description' => $ticket->description,
            ],
        ]);
    }

    /**
     * Update a ticket's details.
     */
    public function update(UpdateTicketRequest $request, Ticket $ticket): RedirectResponse
    {
        $ticket->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'The ticket has been updated.']);

        return redirect()->route('tickets.show', $ticket);
    }

    /**
     * Delete a ticket together with its comments.
     */
    public function destroy(Ticket $ticket): RedirectResponse
    {
        Gate::authorize('delete', $ticket);

        $group = TicketGroup::forType($ticket->type);
        $ticket->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$ticket->key} has been deleted."]);

        return redirect()->route('tickets.index', ['group' => $group->value]);
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
     * Count the tickets the user can see in each group.
     *
     * @return list<array{key: string, label: string, count: int}>
     */
    private function groupCounts(User $user): array
    {
        $totals = Ticket::visibleTo($user)
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
