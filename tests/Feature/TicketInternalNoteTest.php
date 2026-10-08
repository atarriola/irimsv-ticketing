<?php

use App\Enums\WaitingOn;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('an admin can leave an internal note that the requester never sees', function () {
    Notification::fake();
    $admin = User::factory()->admin()->create();
    $requester = User::factory()->create();
    $ticket = Ticket::factory()->for($requester, 'requester')->create();

    $this->actingAs($admin)
        ->postJson(route('tickets.comments.store', $ticket), ['body' => 'Looks like the known printer bug.', 'is_internal' => true])
        ->assertCreated()
        ->assertJsonPath('comment.is_internal', true);

    Notification::assertNothingSent();

    $this->actingAs($requester)
        ->get(route('tickets.show', $ticket))
        ->assertInertia(fn (Assert $page) => $page->has('comments', 0)->where('can.addInternalNote', false));

    $this->actingAs($requester)
        ->getJson(route('tickets.comments.index', $ticket))
        ->assertJsonCount(0, 'data');

    $this->actingAs($admin)
        ->get(route('tickets.show', $ticket))
        ->assertInertia(fn (Assert $page) => $page->has('comments', 1)->where('comments.0.is_internal', true)->where('can.addInternalNote', true));
});

test('an internal note does not pass the turn to the requester', function () {
    $admin = User::factory()->admin()->create();
    $ticket = Ticket::factory()->create();

    $this->actingAs($admin)->postJson(route('tickets.comments.store', $ticket), ['body' => 'Note to self.', 'is_internal' => true]);

    expect($ticket->fresh()->waiting_on)->toBe(WaitingOn::Support);
});

test('a member cannot leave an internal note', function () {
    $requester = User::factory()->create();
    $ticket = Ticket::factory()->for($requester, 'requester')->create();

    $this->actingAs($requester)
        ->postJson(route('tickets.comments.store', $ticket), ['body' => 'Secret', 'is_internal' => true])
        ->assertForbidden();

    expect(TicketComment::count())->toBe(0);
});
