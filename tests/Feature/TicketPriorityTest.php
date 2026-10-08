<?php

use App\Enums\TicketEventKind;
use App\Enums\TicketPriority;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('an admin can change the priority from the ticket page and it is noted on the timeline', function () {
    $admin = User::factory()->admin()->create();
    $ticket = Ticket::factory()->create(['priority' => TicketPriority::Low]);

    $this->actingAs($admin)
        ->patch(route('tickets.priority.update', $ticket), ['priority' => 'critical'])
        ->assertRedirect()
        ->assertInertiaFlash('toast.message', 'The priority is now Critical.');

    expect($ticket->fresh()->priority)->toBe(TicketPriority::Critical);

    $event = $ticket->events()->where('kind', TicketEventKind::PriorityChanged)->sole();

    expect($event->user_id)->toBe($admin->id)
        ->and($event->details)->toBe(['from' => 'low', 'to' => 'critical']);
});

test('setting the same priority again changes nothing', function () {
    $ticket = Ticket::factory()->create(['priority' => TicketPriority::High]);

    $this->actingAs(User::factory()->admin()->create())->patch(route('tickets.priority.update', $ticket), ['priority' => 'high']);

    expect($ticket->events()->count())->toBe(0);
});

test(':who cannot change the priority from the ticket page', function (string $who) {
    $requester = User::factory()->create();
    $ticket = Ticket::factory()->for($requester, 'requester')->shared()->create(['priority' => TicketPriority::Low]);

    $this->actingAs($who === 'the requester' ? $requester : User::factory()->create())
        ->patch(route('tickets.priority.update', $ticket), ['priority' => 'critical'])
        ->assertForbidden();

    expect($ticket->fresh()->priority)->toBe(TicketPriority::Low);
})->with(['the requester', 'another member']);

test('a requester editing their ticket cannot change its priority', function () {
    $requester = User::factory()->create();
    $ticket = Ticket::factory()->for($requester, 'requester')->create(['priority' => TicketPriority::Low]);

    $this->actingAs($requester)
        ->get(route('tickets.edit', $ticket))
        ->assertInertia(fn (Assert $page) => $page->where('canSetPriority', false));

    $this->actingAs($requester)
        ->put(route('tickets.update', $ticket), ['type' => 'bug_report', 'priority' => 'critical', 'subject' => 'New subject', 'description' => 'Still here'])
        ->assertRedirect(route('tickets.show', $ticket));

    $ticket->refresh();

    expect($ticket->priority)->toBe(TicketPriority::Low)
        ->and($ticket->subject)->toBe('New subject');
});

test('an admin editing a ticket can change its priority', function () {
    $ticket = Ticket::factory()->create(['priority' => TicketPriority::Low]);

    $this->actingAs(User::factory()->admin()->create())
        ->put(route('tickets.update', $ticket), ['type' => 'bug_report', 'priority' => 'high', 'subject' => 'Subject', 'description' => 'Description']);

    expect($ticket->fresh()->priority)->toBe(TicketPriority::High)
        ->and($ticket->events()->where('kind', TicketEventKind::PriorityChanged)->count())->toBe(1);
});

test('the priority is chosen when a ticket is raised', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('tickets.create'))
        ->assertInertia(fn (Assert $page) => $page->where('canSetPriority', true));
});
