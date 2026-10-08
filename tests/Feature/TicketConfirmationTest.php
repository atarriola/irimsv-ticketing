<?php

use App\Enums\TicketStatus;
use App\Enums\WaitingOn;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('a resolved ticket asks its requester to confirm the fix', function () {
    $requester = User::factory()->create();
    $ticket = Ticket::factory()->for($requester, 'requester')->resolved()->create();

    $this->actingAs($requester)
        ->get(route('tickets.show', $ticket))
        ->assertInertia(fn (Assert $page) => $page
            ->where('ticket.awaiting_confirmation', true)
            ->where('can.confirmResolution', true)
            ->where('can.changeStatus', false));
});

test('the requester can confirm a resolved ticket, which closes it', function () {
    $requester = User::factory()->create();
    $ticket = Ticket::factory()->for($requester, 'requester')->resolved()->create();

    $this->actingAs($requester)
        ->patch(route('tickets.status.update', $ticket), ['status' => 'closed'])
        ->assertRedirect()
        ->assertInertiaFlash('toast.message', 'Thanks for confirming. The ticket is now closed.');

    $ticket->refresh();

    expect($ticket->status)->toBe(TicketStatus::Closed)
        ->and($ticket->closed_at)->not->toBeNull()
        ->and($ticket->waiting_on)->toBeNull()
        ->and($ticket->events()->latest('id')->first()->user_id)->toBe($requester->id);
});

test('the requester can reopen a resolved ticket', function () {
    $requester = User::factory()->create();
    $ticket = Ticket::factory()->for($requester, 'requester')->resolved()->create();

    $this->actingAs($requester)
        ->patch(route('tickets.status.update', $ticket), ['status' => 'open'])
        ->assertInertiaFlash('toast.message', 'The ticket has been reopened.');

    $ticket->refresh();

    expect($ticket->status)->toBe(TicketStatus::Open)
        ->and($ticket->resolved_at)->toBeNull()
        ->and($ticket->waiting_on)->toBe(WaitingOn::Support);
});

test('the requester cannot move a resolved ticket anywhere but open or closed', function () {
    $requester = User::factory()->create();
    $ticket = Ticket::factory()->for($requester, 'requester')->resolved()->create();

    $this->actingAs($requester)
        ->patch(route('tickets.status.update', $ticket), ['status' => 'in_progress'])
        ->assertSessionHasErrors('status');

    expect($ticket->fresh()->status)->toBe(TicketStatus::Resolved);
});

test('the requester cannot reopen a ticket resolved too long ago', function () {
    config()->set('helpdesk.reopen_window_days', 14);
    $requester = User::factory()->create();
    $ticket = Ticket::factory()->for($requester, 'requester')->resolved()->create(['resolved_at' => now()->subDays(15)]);

    $this->actingAs($requester)
        ->patch(route('tickets.status.update', $ticket), ['status' => 'open'])
        ->assertForbidden();

    $this->actingAs($requester)
        ->get(route('tickets.show', $ticket))
        ->assertInertia(fn (Assert $page) => $page->where('can.confirmResolution', false));
});

test('the requester cannot change the status of an open ticket', function () {
    $requester = User::factory()->create();
    $ticket = Ticket::factory()->for($requester, 'requester')->create();

    $this->actingAs($requester)
        ->patch(route('tickets.status.update', $ticket), ['status' => 'closed'])
        ->assertForbidden();
});

test('a feature request moves through its own statuses', function (string $type, string $status, bool $isAllowed) {
    $ticket = Ticket::factory()->create(['type' => $type]);

    $response = $this->actingAs(User::factory()->admin()->create())->patch(route('tickets.status.update', $ticket), ['status' => $status]);

    if ($isAllowed) {
        $response->assertSessionDoesntHaveErrors();
        expect($ticket->fresh()->status->value)->toBe($status);
    } else {
        $response->assertSessionHasErrors('status');
        expect($ticket->fresh()->status)->toBe(TicketStatus::Open);
    }
})->with([
    'a feature request under review' => ['feature_request', 'under_review', true],
    'a feature request planned' => ['feature_request', 'planned', true],
    'a feature request shipped' => ['feature_request', 'shipped', true],
    'a feature request resolved' => ['feature_request', 'resolved', false],
    'a bug planned' => ['bug_report', 'planned', false],
    'a bug resolved' => ['bug_report', 'resolved', true],
]);

test('shipping a feature request counts as resolving it', function () {
    $ticket = Ticket::factory()->featureRequest()->create();

    $this->actingAs(User::factory()->admin()->create())->patch(route('tickets.status.update', $ticket), ['status' => 'shipped']);

    $ticket->refresh();

    expect($ticket->resolved_at)->not->toBeNull()
        ->and($ticket->waiting_on)->toBe(WaitingOn::Requester)
        ->and($ticket->isAwaitingConfirmation())->toBeTrue();
});

test('the board of feature requests has a column for each of their statuses', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get(route('tickets.index', ['group' => 'feature_requests']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('columns', 6)
            ->where('columns.1.status', 'under_review')
            ->where('columns.2.status', 'planned')
            ->where('columns.4.status', 'shipped'));
});

test('the ticket page offers only the statuses of the ticket type', function () {
    $ticket = Ticket::factory()->create(['type' => 'bug_report']);

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('tickets.show', $ticket))
        ->assertInertia(fn (Assert $page) => $page
            ->has('statuses', 4)
            ->where('statuses.1.value', 'in_progress'));
});
