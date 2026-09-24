<?php

use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('an admin can resolve, close and reopen a ticket', function () {
    $admin = User::factory()->admin()->create();
    $ticket = Ticket::factory()->create();

    $this->actingAs($admin)
        ->patch(route('tickets.status.update', $ticket), ['status' => 'resolved'])
        ->assertRedirect()
        ->assertInertiaFlash('toast.message', 'The ticket is now marked as Resolved.');

    expect($ticket->fresh()->status)->toBe(TicketStatus::Resolved);
    expect($ticket->fresh()->resolved_at)->not->toBeNull();

    $this->actingAs($admin)->patch(route('tickets.status.update', $ticket), ['status' => 'closed']);

    expect($ticket->fresh()->status)->toBe(TicketStatus::Closed);
    expect($ticket->fresh()->closed_at)->not->toBeNull();

    $this->actingAs($admin)->patch(route('tickets.status.update', $ticket), ['status' => 'open']);

    expect($ticket->fresh()->status)->toBe(TicketStatus::Open);
    expect($ticket->fresh()->resolved_at)->toBeNull();
    expect($ticket->fresh()->closed_at)->toBeNull();
});

test('a status that does not exist is rejected', function () {
    $ticket = Ticket::factory()->create();

    $this->actingAs(User::factory()->admin()->create())
        ->patch(route('tickets.status.update', $ticket), ['status' => 'archived'])
        ->assertSessionHasErrors(['status' => 'The selected status is invalid.']);

    expect($ticket->fresh()->status)->toBe(TicketStatus::Open);
});

test('a requester cannot change the status of their own ticket', function () {
    $requester = User::factory()->create();
    $ticket = Ticket::factory()->for($requester, 'requester')->create();

    $this->actingAs($requester)
        ->patch(route('tickets.status.update', $ticket), ['status' => 'closed'])
        ->assertForbidden();

    expect($ticket->fresh()->status)->toBe(TicketStatus::Open);
});

test('tickets cannot be assigned to anyone', function () {
    $ticket = Ticket::factory()->create();

    $this->actingAs(User::factory()->admin()->create())
        ->patch("/tickets/{$ticket->id}/assignment", ['assigned_to' => 1])
        ->assertNotFound();
});
