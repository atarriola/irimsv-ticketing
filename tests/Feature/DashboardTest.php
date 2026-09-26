<?php

use App\Enums\TicketPriority;
use App\Enums\TicketType;
use App\Models\ForumReply;
use App\Models\ForumThread;
use App\Models\NewsPost;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/**
 * Read the count for one key out of a list of dashboard counts.
 *
 * @param  list<array{key: string, label: string, count: int}>  $counts
 */
function dashboardCount(array $counts, string $key): int
{
    return collect($counts)->firstWhere('key', $key)['count'];
}

test('a guest is sent to the login page when opening the dashboard', function () {
    $this->get(route('dashboard'))->assertRedirect(route('login'));
});

test('a user sees status counts for their own tickets only', function () {
    $user = User::factory()->create();
    Ticket::factory(2)->for($user, 'requester')->create();
    Ticket::factory()->for($user, 'requester')->resolved()->create();
    Ticket::factory(3)->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('statusCounts', fn ($counts) => dashboardCount($counts->all(), 'open') === 2
                && dashboardCount($counts->all(), 'in_progress') === 0
                && dashboardCount($counts->all(), 'resolved') === 1
                && dashboardCount($counts->all(), 'closed') === 0));
});

test('an admin sees status counts across every ticket', function () {
    $admin = User::factory()->admin()->create();
    Ticket::factory(3)->create();
    Ticket::factory(2)->closed()->create();

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('statusCounts', fn ($counts) => dashboardCount($counts->all(), 'open') === 3
                && dashboardCount($counts->all(), 'closed') === 2));
});

test('a user never receives tickets raised by someone else', function () {
    $user = User::factory()->create();
    $ownTicket = Ticket::factory()->for($user, 'requester')->create();
    Ticket::factory(2)->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('recentTickets', 1)
            ->where('recentTickets.0.id', $ownTicket->id));
});

test('urgency and type breakdowns count active tickets only', function () {
    $admin = User::factory()->admin()->create();
    Ticket::factory(2)->create(['priority' => TicketPriority::Critical, 'type' => TicketType::BugReport]);
    Ticket::factory()->inProgress()->create(['priority' => TicketPriority::Low, 'type' => TicketType::FeatureRequest]);
    Ticket::factory()->resolved()->create(['priority' => TicketPriority::Critical, 'type' => TicketType::BugReport]);

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('priorityCounts.0.key', 'critical')
            ->where('priorityCounts', fn ($counts) => dashboardCount($counts->all(), 'critical') === 2
                && dashboardCount($counts->all(), 'low') === 1)
            ->where('typeCounts', fn ($counts) => dashboardCount($counts->all(), 'bug_report') === 2
                && dashboardCount($counts->all(), 'feature_request') === 1
                && dashboardCount($counts->all(), 'problem') === 0));
});

test('the dashboard lists the latest forum threads with their reply counts', function () {
    $thread = ForumThread::factory()->pinned()->create(['body' => 'Is the portal down?']);
    ForumReply::factory(3)->for($thread, 'thread')->create();

    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('recentThreads', 1)
            ->where('recentThreads.0.excerpt', 'Is the portal down?')
            ->where('recentThreads.0.replies_count', 3)
            ->where('recentThreads.0.is_pinned', true));
});

test('the dashboard shows the three latest published news posts', function () {
    NewsPost::factory()->draft()->create(['title' => 'Still a draft']);
    NewsPost::factory()->create(['title' => 'Oldest', 'published_at' => now()->subDays(4)]);
    NewsPost::factory()->create(['title' => 'Third', 'published_at' => now()->subDays(3)]);
    NewsPost::factory()->create(['title' => 'Second', 'published_at' => now()->subDays(2)]);
    NewsPost::factory()->create(['title' => 'Newest', 'published_at' => now()->subDay()]);

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('latestNews', 3)
            ->where('latestNews.0.title', 'Newest')
            ->where('latestNews.1.title', 'Second')
            ->where('latestNews.2.title', 'Third'));
});
