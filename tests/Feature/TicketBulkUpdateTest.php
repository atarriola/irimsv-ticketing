<?php

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\User;
use App\Notifications\TicketStatusChanged;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

test('an admin can move several tickets to a status and give them a priority at once', function () {
    Notification::fake();
    $admin = User::factory()->admin()->create();
    $tickets = Ticket::factory(3)->create(['type' => 'bug_report', 'priority' => TicketPriority::Low]);
    $untouched = Ticket::factory()->create(['type' => 'bug_report', 'priority' => TicketPriority::Low]);

    $this->actingAs($admin)
        ->patch(route('tickets.bulk.update'), ['ids' => $tickets->modelKeys(), 'status' => 'in_progress', 'priority' => 'high'])
        ->assertRedirect()
        ->assertInertiaFlash('toast.message', '3 tickets have been updated.');

    $tickets->each(function (Ticket $ticket): void {
        $ticket->refresh();
        expect($ticket->status)->toBe(TicketStatus::InProgress)
            ->and($ticket->priority)->toBe(TicketPriority::High)
            ->and($ticket->events()->count())->toBe(2);
    });
    expect($untouched->fresh()->status)->toBe(TicketStatus::Open);
    Notification::assertSentTimes(TicketStatusChanged::class, 3);
});

test('a status that does not fit a ticket leaves that ticket alone', function () {
    $admin = User::factory()->admin()->create();
    $bug = Ticket::factory()->create(['type' => 'bug_report']);
    $request = Ticket::factory()->featureRequest()->create();

    $this->actingAs($admin)
        ->patch(route('tickets.bulk.update'), ['ids' => [$bug->id, $request->id], 'status' => 'planned'])
        ->assertInertiaFlash('toast.message', '1 ticket has been updated.');

    expect($bug->fresh()->status)->toBe(TicketStatus::Open)
        ->and($request->fresh()->status)->toBe(TicketStatus::Planned);
});

test('a bulk change needs tickets and something to apply', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->patch(route('tickets.bulk.update'), ['ids' => [], 'status' => 'closed'])
        ->assertSessionHasErrors(['ids' => 'Select at least one ticket.']);

    $this->actingAs($admin)
        ->patch(route('tickets.bulk.update'), ['ids' => [1]])
        ->assertSessionHasErrors(['status', 'priority']);
});

test('a member cannot change tickets in bulk', function () {
    $ticket = Ticket::factory()->create();

    $this->actingAs(User::factory()->create())
        ->patch(route('tickets.bulk.update'), ['ids' => [$ticket->id], 'status' => 'closed'])
        ->assertForbidden();

    expect($ticket->fresh()->status)->toBe(TicketStatus::Open);
});
