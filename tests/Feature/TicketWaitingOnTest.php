<?php

use App\Enums\WaitingOn;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('a new ticket waits on the helpdesk', function () {
    $this->actingAs(User::factory()->create())->post(route('tickets.store'), ['type' => 'problem', 'priority' => 'low', 'subject' => 'Help', 'description' => 'Please.']);

    $ticket = Ticket::sole();

    expect($ticket->waiting_on)->toBe(WaitingOn::Support)
        ->and($ticket->last_activity_at)->not->toBeNull();
});

test('the turn passes to the requester when the helpdesk replies, and back when they answer', function () {
    $admin = User::factory()->admin()->create();
    $requester = User::factory()->create();
    $ticket = Ticket::factory()->for($requester, 'requester')->create();

    $this->actingAs($admin)->postJson(route('tickets.comments.store', $ticket), ['body' => 'Can you send a screenshot?']);
    expect($ticket->fresh()->waiting_on)->toBe(WaitingOn::Requester);

    $this->actingAs($requester)->postJson(route('tickets.comments.store', $ticket), ['body' => 'Here it is.']);
    expect($ticket->fresh()->waiting_on)->toBe(WaitingOn::Support);
});

test('a resolved ticket waits on the requester and a closed one on nobody', function () {
    $admin = User::factory()->admin()->create();
    $ticket = Ticket::factory()->create();

    $this->actingAs($admin)->patch(route('tickets.status.update', $ticket), ['status' => 'resolved']);
    expect($ticket->fresh()->waiting_on)->toBe(WaitingOn::Requester);

    $this->actingAs($admin)->patch(route('tickets.status.update', $ticket), ['status' => 'closed']);
    expect($ticket->fresh()->waiting_on)->toBeNull();
});

test('a reply moves the ticket up the board', function () {
    $admin = User::factory()->admin()->create();
    $stale = Ticket::factory()->create(['type' => 'bug_report', 'priority' => 'medium', 'last_activity_at' => now()->subDay()]);
    $fresh = Ticket::factory()->create(['type' => 'bug_report', 'priority' => 'medium', 'last_activity_at' => now()->subHour()]);

    $this->actingAs($admin)->postJson(route('tickets.comments.store', $stale), ['body' => 'Any news?']);

    $this->actingAs($admin)
        ->get(route('tickets.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('columns.0.tickets.0.id', $stale->id)
            ->where('columns.0.tickets.1.id', $fresh->id));
});

test('the list can be narrowed to whose turn it is and sorted by activity', function () {
    $admin = User::factory()->admin()->create();
    $answered = Ticket::factory()->create(['type' => 'bug_report']);
    TicketComment::factory()->for($answered)->for($admin, 'author')->create();
    $unanswered = Ticket::factory()->create(['type' => 'bug_report', 'last_activity_at' => now()->subMinutes(5)]);

    $this->actingAs($admin)
        ->get(route('tickets.index', ['view' => 'list', 'waiting' => 'support']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.waiting', 'support')
            ->has('tickets.data', 1)
            ->where('tickets.data.0.id', $unanswered->id)
            ->where('tickets.data.0.waiting_on', 'support')
            ->where('tickets.data.0.waiting_label', 'Waiting on support'));

    $this->actingAs($admin)
        ->get(route('tickets.index', ['view' => 'list', 'sort' => 'active']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('tickets.data.0.id', $answered->id)
            ->where('tickets.data.1.id', $unanswered->id));
});

test('the list can be narrowed to the tickets the user raised and to a category', function () {
    $member = User::factory()->create();
    $mine = Ticket::factory()->for($member, 'requester')->create(['type' => 'bug_report']);
    $shared = Ticket::factory()->shared()->create(['type' => 'bug_report']);

    $this->actingAs($member)
        ->get(route('tickets.index', ['view' => 'list', 'mine' => 1]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.mine', true)
            ->has('tickets.data', 1)
            ->where('tickets.data.0.id', $mine->id));

    $this->actingAs($member)
        ->get(route('tickets.index', ['view' => 'list', 'category' => $shared->category_id]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.category', $shared->category_id)
            ->has('tickets.data', 1)
            ->where('tickets.data.0.id', $shared->id));
});
