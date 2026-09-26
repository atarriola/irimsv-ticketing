<?php

namespace App\Http\Controllers;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Enums\TicketType;
use App\Http\Resources\ForumThreadResource;
use App\Http\Resources\NewsPostResource;
use App\Http\Resources\TicketResource;
use App\Models\ForumThread;
use App\Models\NewsPost;
use App\Models\Ticket;
use App\Models\User;
use BackedEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Display the dashboard for the signed-in user.
     */
    public function __invoke(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Dashboard', [
            'statusCounts' => $this->countsBy(Ticket::visibleTo($user), 'status', TicketStatus::cases()),
            'priorityCounts' => $this->countsBy(Ticket::visibleTo($user)->active(), 'priority', array_reverse(TicketPriority::cases())),
            'typeCounts' => $this->countsBy(Ticket::visibleTo($user)->active(), 'type', TicketType::cases()),
            'recentTickets' => $this->recentTickets($request),
            'recentThreads' => $this->recentThreads($request),
            'latestNews' => $this->latestNews($request),
        ]);
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
     * Get the latest tickets the user is allowed to see.
     *
     * @return list<array{id: int, subject: string, requester: string, category: string|null, type: string, status: string, priority: string, created_at: string}>
     */
    private function recentTickets(Request $request): array
    {
        return Ticket::visibleTo($request->user())
            ->with(['requester:'.User::DISPLAY_COLUMNS, 'category:id,name'])
            ->latest()
            ->latest('id')
            ->limit(6)
            ->get()
            ->map(fn (Ticket $ticket): array => TicketResource::make($ticket)->resolve($request))
            ->all();
    }

    /**
     * Get the latest forum threads.
     *
     * @return list<array{id: int, title: string, type: string, author: string, replies_count: int, is_pinned: bool, is_locked: bool, created_at: string, last_activity_at: string}>
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
