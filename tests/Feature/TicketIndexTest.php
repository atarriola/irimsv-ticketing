<?php

use App\Enums\TicketType;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('bugs and problems are listed together by default', function () {
    $user = User::factory()->create();
    $bug = Ticket::factory()->for($user, 'requester')->create(['type' => TicketType::BugReport]);
    $problem = Ticket::factory()->for($user, 'requester')->create(['type' => TicketType::Problem]);
    Ticket::factory()->for($user, 'requester')->featureRequest()->create();

    $this->actingAs($user)
        ->get(route('tickets.index', ['view' => 'list']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Tickets/Index')
            ->where('group', 'issues')
            ->has('tickets.data', 2)
            ->where('tickets.data.0.id', $problem->id)
            ->where('tickets.data.1.id', $bug->id));
});

test('feature requests are listed separately', function () {
    $user = User::factory()->create();
    Ticket::factory()->for($user, 'requester')->create(['type' => TicketType::BugReport]);
    $featureRequest = Ticket::factory()->for($user, 'requester')->featureRequest()->create();

    $this->actingAs($user)
        ->get(route('tickets.index', ['view' => 'list', 'group' => 'feature_requests']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('group', 'feature_requests')
            ->has('tickets.data', 1)
            ->where('tickets.data.0.id', $featureRequest->id)
            ->where('tickets.data.0.type', 'Feature request'));
});

test('every type of ticket is listed together in the all group', function () {
    $user = User::factory()->create();
    $bug = Ticket::factory()->for($user, 'requester')->create(['type' => TicketType::BugReport]);
    $featureRequest = Ticket::factory()->for($user, 'requester')->featureRequest()->create();
    Ticket::factory()->featureRequest()->create();

    $this->actingAs($user)
        ->get(route('tickets.index', ['view' => 'list', 'group' => 'all']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('group', 'all')
            ->has('tickets.data', 2)
            ->where('tickets.data.0.id', $featureRequest->id)
            ->where('tickets.data.1.id', $bug->id));
});

test('the board of the all group holds every type of ticket', function () {
    $user = User::factory()->create();
    Ticket::factory()->for($user, 'requester')->create(['type' => TicketType::Problem]);
    Ticket::factory()->for($user, 'requester')->featureRequest()->create();

    $this->actingAs($user)
        ->get(route('tickets.index', ['group' => 'all']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('view', 'board')
            ->where('columns.0.status', 'open')
            ->where('columns.0.total', 2));
});

test('an unknown group falls back to bugs and problems', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('tickets.index', ['view' => 'list', 'group' => 'everything']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('group', 'issues'));
});

test('a user only sees and counts their own tickets', function () {
    $user = User::factory()->create();
    $ownTicket = Ticket::factory()->for($user, 'requester')->create(['type' => TicketType::Problem]);
    Ticket::factory(2)->create(['type' => TicketType::BugReport]);
    Ticket::factory()->featureRequest()->create();

    $this->actingAs($user)
        ->get(route('tickets.index', ['view' => 'list']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('tickets.data', 1)
            ->where('tickets.data.0.id', $ownTicket->id)
            ->where('groups.0.key', 'all')
            ->where('groups.0.count', 1)
            ->where('groups.1.key', 'issues')
            ->where('groups.1.count', 1)
            ->where('groups.2.key', 'feature_requests')
            ->where('groups.2.count', 0));
});

test('an admin sees and counts tickets from every user', function () {
    Ticket::factory(2)->create(['type' => TicketType::BugReport]);
    Ticket::factory()->create(['type' => TicketType::Problem]);
    Ticket::factory(2)->featureRequest()->create();

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('tickets.index', ['view' => 'list']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('tickets.data', 3)
            ->where('groups.0.count', 5)
            ->where('groups.1.count', 3)
            ->where('groups.2.count', 2));
});

test('the ticket list is paginated and keeps the group in its page links', function () {
    $user = User::factory()->create();
    Ticket::factory(21)->for($user, 'requester')->featureRequest()->create();

    $this->actingAs($user)
        ->get(route('tickets.index', ['view' => 'list', 'group' => 'feature_requests']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('view', 'list')
            ->where('columns', null)
            ->has('tickets.data', 20)
            ->where('tickets.last_page', 2)
            ->where('tickets.next_page_url', fn (string $url) => str_contains($url, 'group=feature_requests') && str_contains($url, 'page=2')));
});
