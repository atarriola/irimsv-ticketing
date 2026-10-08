<?php

use App\Enums\TicketEventKind;
use App\Models\Ticket;
use App\Models\TicketEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('raising a ticket starts its timeline', function () {
    $requester = User::factory()->create();

    $this->actingAs($requester)->post(route('tickets.store'), ['type' => 'bug_report', 'priority' => 'low', 'subject' => 'Broken', 'description' => 'Very.']);

    $event = Ticket::sole()->events()->sole();

    expect($event->kind)->toBe(TicketEventKind::Created)
        ->and($event->user_id)->toBe($requester->id);
});

test('status changes are noted with who made them and from where to where', function () {
    $admin = User::factory()->admin()->create();
    $ticket = Ticket::factory()->create();

    $this->actingAs($admin)->patch(route('tickets.status.update', $ticket), ['status' => 'in_progress']);
    $this->actingAs($admin)->patch(route('tickets.status.update', $ticket), ['status' => 'resolved']);

    $events = $ticket->events()->get();

    expect($events)->toHaveCount(2)
        ->and($events[0]->kind)->toBe(TicketEventKind::StatusChanged)
        ->and($events[0]->details)->toBe(['from' => 'open', 'to' => 'in_progress'])
        ->and($events[1]->details)->toBe(['from' => 'in_progress', 'to' => 'resolved'])
        ->and($events[1]->user_id)->toBe($admin->id);
});

test('the ticket page tells the timeline in plain words', function () {
    $admin = User::factory()->admin()->create(['firstname' => 'Jose', 'lastname' => 'Cruz']);
    $ticket = Ticket::factory()->create();
    TicketEvent::factory()->for($ticket)->for($admin)->create(['kind' => TicketEventKind::StatusChanged, 'details' => ['from' => 'open', 'to' => 'in_progress']]);
    TicketEvent::factory()->for($ticket)->for($admin)->create(['kind' => TicketEventKind::PriorityChanged, 'details' => ['from' => 'low', 'to' => 'high']]);
    TicketEvent::factory()->for($ticket)->for($ticket->requester)->create(['kind' => TicketEventKind::Edited, 'details' => ['fields' => ['subject', 'description', 'category_id']]]);
    TicketEvent::factory()->for($ticket)->create(['user_id' => null, 'kind' => TicketEventKind::StatusChanged, 'details' => ['from' => 'resolved', 'to' => 'closed']]);

    $this->actingAs($admin)
        ->get(route('tickets.show', $ticket))
        ->assertInertia(fn (Assert $page) => $page
            ->has('timeline', 4)
            ->where('timeline.0.actor', 'Jose Cruz')
            ->where('timeline.0.actor_is_admin', true)
            ->where('timeline.0.description', 'moved it from Open to In progress')
            ->where('timeline.1.description', 'changed the priority from Low to High')
            ->where('timeline.2.description', 'edited the subject, description and category')
            ->where('timeline.3.actor', 'The helpdesk')
            ->where('timeline.3.description', 'moved it from Resolved to Closed'));
});

test('a member sees the timeline of a shared ticket too', function () {
    $ticket = Ticket::factory()->shared()->create();
    TicketEvent::factory()->for($ticket)->create();

    $this->actingAs(User::factory()->create())
        ->get(route('tickets.show', $ticket))
        ->assertInertia(fn (Assert $page) => $page->has('timeline', 1));
});
