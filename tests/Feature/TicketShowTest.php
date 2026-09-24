<?php

use App\Enums\TicketType;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\User;
use App\Models\Usertype;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('a guest is sent to the login page when opening a ticket', function () {
    $ticket = Ticket::factory()->create();

    $this->get(route('tickets.show', $ticket))->assertRedirect(route('login'));
});

test('a requester sees their ticket, its comments in order and what they may do', function () {
    $requester = User::factory()->for(Usertype::factory()->create(['type_name' => 'School Head']))->create();
    $ticket = Ticket::factory()->for($requester, 'requester')->create(['subject' => 'Printer offline', 'type' => TicketType::Problem]);
    $adminComment = TicketComment::factory()->for($ticket)->for(User::factory()->admin(), 'author')->create(['created_at' => now()->subHour()]);
    $ownComment = TicketComment::factory()->for($ticket)->for($requester, 'author')->create();

    $this->actingAs($requester)
        ->get(route('tickets.show', $ticket))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Tickets/Show')
            ->where('ticket.subject', 'Printer offline')
            ->where('ticket.group', 'issues')
            ->where('ticket.requester.name', $requester->name)
            ->where('ticket.requester.position', 'School Head')
            ->missing('ticket.assignee')
            ->has('comments', 2)
            ->where('comments.0.id', $adminComment->id)
            ->where('comments.0.author_is_admin', true)
            ->where('comments.0.can.delete', false)
            ->where('comments.1.id', $ownComment->id)
            ->where('comments.1.can.delete', true)
            ->where('can.update', true)
            ->where('can.comment', true)
            ->where('can.delete', false)
            ->where('can.changeStatus', false)
            ->missing('can.assign'));
});

test('an admin can open any ticket and may manage it', function () {
    $ticket = Ticket::factory()->create();

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('tickets.show', $ticket))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('can.update', true)
            ->where('can.changeStatus', true)
            ->where('can.delete', true));
});

test('a user cannot open a ticket raised by someone else', function () {
    $ticket = Ticket::factory()->create();

    $this->actingAs(User::factory()->create())
        ->get(route('tickets.show', $ticket))
        ->assertForbidden();
});

test('a closed ticket tells its requester they cannot comment', function () {
    $requester = User::factory()->create();
    $ticket = Ticket::factory()->for($requester, 'requester')->closed()->create();

    $this->actingAs($requester)
        ->get(route('tickets.show', $ticket))
        ->assertInertia(fn (Assert $page) => $page
            ->where('can.comment', false)
            ->where('can.update', false));
});

test('the ticket list can be narrowed to one status', function () {
    $user = User::factory()->create();
    Ticket::factory()->for($user, 'requester')->create(['type' => TicketType::BugReport]);
    $resolved = Ticket::factory()->for($user, 'requester')->resolved()->create(['type' => TicketType::BugReport]);

    $this->actingAs($user)
        ->get(route('tickets.index', ['view' => 'list', 'status' => 'resolved']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.status', 'resolved')
            ->has('tickets.data', 1)
            ->where('tickets.data.0.id', $resolved->id)
            ->where('groups.1.count', 2));
});
