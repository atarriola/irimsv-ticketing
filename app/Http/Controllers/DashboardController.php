<?php

namespace App\Http\Controllers;

use App\Enums\NewsKind;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Enums\TicketType;
use App\Enums\WaitingOn;
use App\Http\Resources\ForumThreadResource;
use App\Http\Resources\NewsPostResource;
use App\Http\Resources\TicketResource;
use App\Models\Category;
use App\Models\ForumThread;
use App\Models\NewsPost;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\User;
use BackedEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * How far back the helpdesk's figures look.
     */
    private const int METRICS_DAYS = 30;

    /**
     * Display the dashboard for the signed-in user: the helpdesk's for an administrator, their own for a member.
     */
    public function __invoke(Request $request): Response
    {
        return $request->user()->isAdmin() ? $this->adminDashboard($request) : $this->memberDashboard($request);
    }

    /**
     * The helpdesk at a glance: what needs a reply, how fast it has been answered, and what is coming in.
     */
    private function adminDashboard(Request $request): Response
    {
        $since = now()->subDays(self::METRICS_DAYS);

        return Inertia::render('Dashboard/Admin', [
            'tiles' => [
                ['key' => 'needs_reply', 'label' => 'Needs a reply', 'count' => Ticket::active()->where('waiting_on', WaitingOn::Support)->count()],
                ['key' => 'active', 'label' => 'Active', 'count' => Ticket::active()->count()],
                ['key' => 'awaiting_confirmation', 'label' => 'Awaiting confirmation', 'count' => Ticket::whereIn('status', [TicketStatus::Resolved, TicketStatus::Shipped])->count()],
                ['key' => 'closed', 'label' => 'Closed in '.self::METRICS_DAYS.' days', 'count' => Ticket::where('status', TicketStatus::Closed)->where('closed_at', '>=', $since)->count()],
            ],
            'metrics' => $this->metrics($since),
            'statusCounts' => $this->countsBy(Ticket::query(), 'status', TicketStatus::cases()),
            'priorityCounts' => $this->countsBy(Ticket::query()->active(), 'priority', array_reverse(TicketPriority::cases())),
            'typeCounts' => $this->countsBy(Ticket::query()->active(), 'type', TicketType::cases()),
            'categoryCounts' => $this->categoryCounts($since),
            'needsReply' => $this->tickets($request, Ticket::active()->where('waiting_on', WaitingOn::Support)->oldest('last_activity_at')->oldest('id'), 8),
            'recentTickets' => $this->tickets($request, Ticket::query()->latest()->latest('id'), 6),
            'recentThreads' => $this->recentThreads($request),
            'latestNews' => $this->latestNews($request),
            'maintenanceNotice' => $this->maintenanceNotice(),
        ]);
    }

    /**
     * A member's own corner: the tickets waiting on them, their active ones, and the ones they follow.
     */
    private function memberDashboard(Request $request): Response
    {
        $user = $request->user();
        $mine = fn (): Builder => Ticket::query()->raisedBy($user);

        return Inertia::render('Dashboard/Member', [
            'tiles' => [
                ['key' => 'active', 'label' => 'Active', 'count' => $mine()->active()->count()],
                ['key' => 'waiting_on_you', 'label' => 'Waiting on you', 'count' => $mine()->where('waiting_on', WaitingOn::Requester)->count()],
                ['key' => 'resolved', 'label' => 'Resolved', 'count' => $mine()->whereIn('status', [TicketStatus::Resolved, TicketStatus::Shipped])->count()],
                ['key' => 'closed', 'label' => 'Closed', 'count' => $mine()->where('status', TicketStatus::Closed)->count()],
            ],
            'awaitingMe' => $this->tickets($request, $mine()->where('waiting_on', WaitingOn::Requester)->mostRecentlyActive(), 6),
            'myTickets' => $this->tickets($request, $mine()->active()->mostRecentlyActive(), 6),
            'followedTickets' => $this->tickets($request, Ticket::query()
                ->whereHas('watchers', fn (Builder $query) => $query->whereKey($user->getKey()))
                ->active()
                ->mostRecentlyActive(), 5),
            'recentThreads' => $this->recentThreads($request),
            'latestNews' => $this->latestNews($request),
            'maintenanceNotice' => $this->maintenanceNotice(),
        ]);
    }

    /**
     * How the helpdesk has been doing lately: time to first reply, time to resolution, and how people rated the help.
     *
     * @return array{tickets_raised: int, first_response_hours: float|null, resolution_hours: float|null, rating_average: float|null, rated_count: int}
     */
    private function metrics(Carbon $since): array
    {
        $firstReply = TicketComment::query()
            ->select('created_at')
            ->whereColumn('ticket_id', 'tickets.id')
            ->where('is_internal', false)
            ->whereIn('user_id', User::administrators()->select('id'))
            ->oldest('id')
            ->limit(1);

        $raised = Ticket::query()
            ->where('created_at', '>=', $since)
            ->select(['id', 'created_at'])
            ->addSelect(['first_reply_at' => $firstReply])
            ->withCasts(['first_reply_at' => 'datetime'])
            ->get();

        $resolved = Ticket::query()
            ->whereNotNull('resolved_at')
            ->where('resolved_at', '>=', $since)
            ->get(['created_at', 'resolved_at']);

        $ratings = Ticket::query()->whereNotNull('rating')->toBase()->selectRaw('avg(rating) as average, count(*) as total')->first();

        return [
            'tickets_raised' => $raised->count(),
            'first_response_hours' => $this->averageHours($raised->filter(fn (Ticket $ticket) => $ticket->first_reply_at !== null)->map(fn (Ticket $ticket): float => $ticket->created_at->diffInMinutes($ticket->first_reply_at))),
            'resolution_hours' => $this->averageHours($resolved->map(fn (Ticket $ticket): float => $ticket->created_at->diffInMinutes($ticket->resolved_at))),
            'rating_average' => $ratings->total > 0 ? round((float) $ratings->average, 1) : null,
            'rated_count' => (int) $ratings->total,
        ];
    }

    /**
     * Turn a list of durations in minutes into an average in hours, or null when there is nothing to average.
     *
     * @param  Collection<int, float>  $minutes
     */
    private function averageHours($minutes): ?float
    {
        return $minutes->isEmpty() ? null : round($minutes->avg() / 60, 1);
    }

    /**
     * Count the tickets raised lately in each category, busiest first.
     *
     * @return list<array{key: string, label: string, count: int}>
     */
    private function categoryCounts(Carbon $since): array
    {
        $totals = Ticket::query()
            ->where('created_at', '>=', $since)
            ->toBase()
            ->select('category_id')
            ->selectRaw('count(*) as total')
            ->groupBy('category_id')
            ->pluck('total', 'category_id');

        $counts = Category::orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Category $category): array => [
                'key' => (string) $category->id,
                'label' => $category->name,
                'count' => (int) ($totals[$category->id] ?? 0),
            ])
            ->push(['key' => 'none', 'label' => 'No category', 'count' => (int) ($totals[''] ?? 0)])
            ->sortByDesc('count')
            ->values();

        return $counts->all();
    }

    /**
     * Get the maintenance notice to show at the top, if one went out recently.
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
     * Get the latest published news.
     *
     * @return list<array<string, mixed>>
     */
    private function latestNews(Request $request): array
    {
        return NewsPost::published()
            ->with('author:'.User::DISPLAY_COLUMNS)
            ->newestFirst()
            ->limit(3)
            ->get()
            ->map(fn (NewsPost $post): array => NewsPostResource::make($post)->resolve($request))
            ->all();
    }

    /**
     * Count the tickets per enum case of the given column, including cases with no tickets.
     *
     * @param  Builder<Ticket>  $query
     * @param  list<BackedEnum>  $cases
     * @return list<array{key: string, label: string, count: int}>
     */
    private function countsBy(Builder $query, string $column, array $cases): array
    {
        $totals = $query->toBase()
            ->select($column)
            ->selectRaw('count(*) as total')
            ->groupBy($column)
            ->pluck('total', $column);

        return array_map(fn (BackedEnum $case): array => [
            'key' => $case->value,
            'label' => $case->label(),
            'count' => (int) ($totals[$case->value] ?? 0),
        ], $cases);
    }

    /**
     * Get a short list of tickets, with what a card needs to show them.
     *
     * @param  Builder<Ticket>  $query
     * @return list<array<string, mixed>>
     */
    private function tickets(Request $request, Builder $query, int $limit): array
    {
        return $query
            ->with(['requester:'.User::DISPLAY_COLUMNS, 'category:id,name'])
            ->withCount('supporters')
            ->limit($limit)
            ->get()
            ->map(fn (Ticket $ticket): array => TicketResource::make($ticket)->resolve($request))
            ->all();
    }

    /**
     * Get the latest forum threads.
     *
     * @return list<array<string, mixed>>
     */
    private function recentThreads(Request $request): array
    {
        return ForumThread::query()
            ->with(['author:'.User::DISPLAY_COLUMNS, 'topic:id,name,slug'])
            ->withCount('replies')
            ->latest()
            ->latest('id')
            ->limit(5)
            ->get()
            ->map(fn (ForumThread $thread): array => ForumThreadResource::make($thread)->resolve($request))
            ->all();
    }
}
