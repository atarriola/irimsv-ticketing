<?php

use App\Enums\TicketPriority;
use App\Enums\TicketType;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('tickets open on a board with one column per status', function () {
    $admin = User::factory()->admin()->create(['firstname' => 'System', 'lastname' => 'Admin']);
    $open = Ticket::factory()->create(['type' => TicketType::BugReport, 'subject' => 'Login fails']);
    $inProgress = Ticket::factory()->inProgress()->create(['type' => TicketType::Problem]);
    TicketComment::factory(2)->for($inProgress)->create();
    Ticket::factory()->resolved()->create(['type' => TicketType::BugReport]);

    $this->actingAs($admin)
        ->get(route('tickets.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Tickets/Index')
            ->where('view', 'board')
            ->where('tickets', null)
            ->has('columns', 4)
            ->where('columns.0.status', 'open')
            ->where('columns.0.label', 'Open')
            ->where('columns.0.total', 1)
            ->where('columns.0.tickets.0.id', $open->id)
            ->where('columns.0.tickets.0.key', "TKT-{$open->id}")
            ->where('columns.0.tickets.0.subject', 'Login fails')
            ->where('columns.0.tickets.0.type_key', 'bug_report')
            ->where('columns.1.status', 'in_progress')
            ->where('columns.1.tickets.0.id', $inProgress->id)
            ->where('columns.1.tickets.0.comments_count', 2)
            ->where('columns.2.total', 1)
            ->where('columns.3.status', 'closed')
            ->where('columns.3.total', 0)
            ->where('columns.3.tickets', []));
});

test('a board column lists its most urgent tickets first', function () {
    $user = User::factory()->create();
    $low = Ticket::factory()->for($user, 'requester')->create(['type' => TicketType::BugReport, 'priority' => TicketPriority::Low]);
    $critical = Ticket::factory()->for($user, 'requester')->create(['type' => TicketType::BugReport, 'priority' => TicketPriority::Critical]);
    $high = Ticket::factory()->for($user, 'requester')->create(['type' => TicketType::BugReport, 'priority' => TicketPriority::High]);

    $this->actingAs($user)
        ->get(route('tickets.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('columns.0.tickets.0.id', $critical->id)
            ->where('columns.0.tickets.1.id', $high->id)
            ->where('columns.0.tickets.2.id', $low->id));
});

test('the board only holds the tickets of the chosen group that the user may see', function () {
    $user = User::factory()->create();
    $ownFeature = Ticket::factory()->for($user, 'requester')->featureRequest()->create();
    Ticket::factory()->for($user, 'requester')->create(['type' => TicketType::BugReport]);
    Ticket::factory()->featureRequest()->create();

    $this->actingAs($user)
        ->get(route('tickets.index', ['group' => 'feature_requests']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('group', 'feature_requests')
            ->where('columns.0.total', 1)
            ->has('columns.0.tickets', 1)
            ->where('columns.0.tickets.0.id', $ownFeature->id));
});

test('only an admin is allowed to move cards', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get(route('tickets.index'))
        ->assertInertia(fn (Assert $page) => $page->where('can.moveCards', true));

    $this->actingAs(User::factory()->create())
        ->get(route('tickets.index'))
        ->assertInertia(fn (Assert $page) => $page->where('can.moveCards', false));
});

test('a column shows at most fifty cards but reports its full total', function () {
    $user = User::factory()->create();
    Ticket::factory(51)->for($user, 'requester')->create(['type' => TicketType::BugReport]);

    $this->actingAs($user)
        ->get(route('tickets.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('columns.0.total', 51)
            ->has('columns.0.tickets', 50));
});

test('tickets can be searched by :case', function (string $term) {
    $user = User::factory()->create();
    $match = Ticket::factory()->for($user, 'requester')->create(['type' => TicketType::BugReport, 'subject' => 'Printer offline', 'description' => 'The third floor device is unreachable.']);
    Ticket::factory()->for($user, 'requester')->create(['type' => TicketType::BugReport, 'subject' => 'Password reset', 'description' => 'Email never arrives.']);

    $this->actingAs($user)
        ->get(route('tickets.index', ['view' => 'list', 'q' => str_replace('{id}', (string) $match->id, $term)]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('tickets.data', 1)
            ->where('tickets.data.0.id', $match->id));
})->with([
    'words in the subject' => ['printer'],
    'words in the description' => ['THIRD FLOOR'],
    'its key' => ['tkt-{id}'],
]);

test('a search term is treated as text, not as a pattern', function () {
    $user = User::factory()->create();
    Ticket::factory()->for($user, 'requester')->create(['type' => TicketType::BugReport, 'subject' => 'Plain subject']);

    $this->actingAs($user)
        ->get(route('tickets.index', ['view' => 'list', 'q' => '%']))
        ->assertInertia(fn (Assert $page) => $page->has('tickets.data', 0));
});

test('searching never reveals tickets raised by someone else', function () {
    Ticket::factory()->create(['type' => TicketType::BugReport, 'subject' => 'Secret payroll issue']);

    $this->actingAs(User::factory()->create())
        ->get(route('tickets.index', ['view' => 'list', 'q' => 'payroll']))
        ->assertInertia(fn (Assert $page) => $page->has('tickets.data', 0));
});

test('tickets can be filtered by priority', function () {
    $urgent = Ticket::factory()->create(['type' => TicketType::BugReport, 'priority' => TicketPriority::High]);
    Ticket::factory(2)->create(['type' => TicketType::BugReport, 'priority' => TicketPriority::Low]);

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('tickets.index', ['view' => 'list', 'priority' => 'high']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.priority', 'high')
            ->has('tickets.data', 1)
            ->where('tickets.data.0.id', $urgent->id));
});

test('tickets carry no assignee anywhere', function () {
    $admin = User::factory()->admin()->create();
    Ticket::factory()->create(['type' => TicketType::BugReport]);

    $this->actingAs($admin)
        ->get(route('tickets.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->missing('columns.0.tickets.0.assignee')
            ->missing('filters.assignee'));
});
