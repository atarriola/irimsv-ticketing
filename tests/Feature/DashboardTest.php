<?php

use App\Enums\NewsKind;
use App\Enums\TicketPriority;
use App\Enums\TicketType;
use App\Enums\WaitingOn;
use App\Models\ForumReply;
use App\Models\ForumThread;
use App\Models\NewsPost;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\TicketWatcher;
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

test('a member sees their own tickets, not everyone else\'s', function () {
    $member = User::factory()->create();
    Ticket::factory(2)->for($member, 'requester')->create();
    Ticket::factory()->for($member, 'requester')->resolved()->create();
    Ticket::factory()->for($member, 'requester')->closed()->create();
    Ticket::factory(3)->create();

    $this->actingAs($member)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard/Member')
            ->where('tiles', fn ($tiles) => dashboardCount($tiles->all(), 'active') === 2
                && dashboardCount($tiles->all(), 'resolved') === 1
                && dashboardCount($tiles->all(), 'closed') === 1)
            ->has('myTickets', 2)
            ->missing('recentTickets'));
});

test('a member is shown the tickets waiting on them first', function () {
    $member = User::factory()->create();
    $admin = User::factory()->admin()->create();
    $answered = Ticket::factory()->for($member, 'requester')->create();
    TicketComment::factory()->for($answered)->for($admin, 'author')->create();
    Ticket::factory()->for($member, 'requester')->create();

    $this->actingAs($member)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('tiles', fn ($tiles) => dashboardCount($tiles->all(), 'waiting_on_you') === 1)
            ->has('awaitingMe', 1)
            ->where('awaitingMe.0.id', $answered->id)
            ->where('awaitingMe.0.waiting_on', WaitingOn::Requester->value));
});

test('a member sees the active tickets they follow', function () {
    $member = User::factory()->create();
    $followed = Ticket::factory()->shared()->create();
    TicketWatcher::factory()->for($followed)->for($member)->create();
    TicketWatcher::factory()->for(Ticket::factory()->shared()->closed()->create())->for($member)->create();

    $this->actingAs($member)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('followedTickets', 1)
            ->where('followedTickets.0.id', $followed->id));
});

test('an admin sees what needs a reply and the counts across every ticket', function () {
    $admin = User::factory()->admin()->create();
    $unanswered = Ticket::factory()->create(['last_activity_at' => now()->subDays(3)]);
    $answered = Ticket::factory()->create();
    TicketComment::factory()->for($answered)->for($admin, 'author')->create();
    Ticket::factory()->resolved()->create();
    Ticket::factory()->closed()->create(['closed_at' => now()->subDay()]);
    Ticket::factory()->closed()->create(['closed_at' => now()->subDays(40)]);

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard/Admin')
            ->where('tiles', fn ($tiles) => dashboardCount($tiles->all(), 'needs_reply') === 1
                && dashboardCount($tiles->all(), 'active') === 2
                && dashboardCount($tiles->all(), 'awaiting_confirmation') === 1
                && dashboardCount($tiles->all(), 'closed') === 1)
            ->has('needsReply', 1)
            ->where('needsReply.0.id', $unanswered->id)
            ->where('statusCounts', fn ($counts) => dashboardCount($counts->all(), 'open') === 2
                && dashboardCount($counts->all(), 'closed') === 2)
            ->has('recentTickets', 5));
});

test('the admin figures cover first replies, resolutions and ratings', function () {
    $admin = User::factory()->admin()->create();
    $ticket = Ticket::factory()->create(['created_at' => now()->subHours(10)]);
    TicketComment::factory()->for($ticket)->for($admin, 'author')->create(['created_at' => now()->subHours(8)]);
    Ticket::factory()->resolved()->create(['created_at' => now()->subHours(30), 'resolved_at' => now()->subHours(6), 'rating' => 4]);
    Ticket::factory()->closed()->create(['rating' => 2]);

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('metrics.tickets_raised', 3)
            ->where('metrics.first_response_hours', fn ($hours): bool => (float) $hours === 2.0)
            ->where('metrics.resolution_hours', fn ($hours): bool => (float) $hours === 24.0)
            ->where('metrics.rating_average', fn ($average): bool => (float) $average === 3.0)
            ->where('metrics.rated_count', 2));
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

test('a recent maintenance notice is shown on the dashboard and the ticket form', function (string $role) {
    $user = $role === 'an admin' ? User::factory()->admin()->create() : User::factory()->create();
    NewsPost::factory()->create(['kind' => NewsKind::Maintenance, 'title' => 'Portal offline on Saturday', 'published_at' => now()->subDays(2)]);
    NewsPost::factory()->create(['kind' => NewsKind::Maintenance, 'title' => 'Old notice', 'published_at' => now()->subDays(30)]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('maintenanceNotice.title', 'Portal offline on Saturday'));

    $this->actingAs($user)
        ->get(route('tickets.create'))
        ->assertInertia(fn (Assert $page) => $page->where('maintenanceNotice.title', 'Portal offline on Saturday'));
})->with(['an admin', 'a member']);

test('no maintenance notice is shown when none went out recently', function () {
    NewsPost::factory()->create(['kind' => NewsKind::Announcement, 'published_at' => now()->subDay()]);
    NewsPost::factory()->draft()->create(['kind' => NewsKind::Maintenance]);

    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('maintenanceNotice', null));
});
