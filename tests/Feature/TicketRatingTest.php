<?php

use App\Enums\TicketEventKind;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('the requester can say how the help was once the ticket is resolved', function () {
    $requester = User::factory()->create();
    $ticket = Ticket::factory()->for($requester, 'requester')->resolved()->create();

    $this->actingAs($requester)
        ->put(route('tickets.rating.update', $ticket), ['rating' => 4, 'rating_comment' => 'Quick and clear.'])
        ->assertRedirect()
        ->assertInertiaFlash('toast.message', 'Thank you for your feedback.');

    $ticket->refresh();

    expect($ticket->rating)->toBe(4)
        ->and($ticket->rating_comment)->toBe('Quick and clear.')
        ->and($ticket->events()->where('kind', TicketEventKind::Rated)->sole()->details)->toBe(['rating' => 4]);

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('tickets.show', $ticket))
        ->assertInertia(fn (Assert $page) => $page
            ->where('ticket.rating', 4)
            ->where('ticket.rating_comment', 'Quick and clear.')
            ->where('can.rate', false));
});

test('the rating must be between one and five', function () {
    $requester = User::factory()->create();
    $ticket = Ticket::factory()->for($requester, 'requester')->closed()->create();

    $this->actingAs($requester)
        ->put(route('tickets.rating.update', $ticket), ['rating' => 6])
        ->assertSessionHasErrors('rating');

    expect($ticket->fresh()->rating)->toBeNull();
});

test(':who cannot rate the ticket', function (string $who) {
    $requester = User::factory()->create();
    $ticket = $who === 'the requester, while it is still open'
        ? Ticket::factory()->for($requester, 'requester')->create()
        : Ticket::factory()->for($requester, 'requester')->shared()->resolved()->create();
    $actor = $who === 'the requester, while it is still open' ? $requester : User::factory()->create();

    $this->actingAs($actor)
        ->put(route('tickets.rating.update', $ticket), ['rating' => 5])
        ->assertForbidden();
})->with(['the requester, while it is still open', 'another member']);
