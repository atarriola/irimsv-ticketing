<?php

use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('a guest cannot read or join a conversation', function (string $method) {
    $ticket = Ticket::factory()->create();

    $this->{$method}(route('tickets.comments.index', $ticket), ['body' => 'Hello'])->assertUnauthorized();

    expect(TicketComment::count())->toBe(0);
})->with(['getJson', 'postJson']);

test(':role can send a message on a ticket and receives it back', function (string $role) {
    $requester = User::factory()->create();
    $ticket = Ticket::factory()->for($requester, 'requester')->create();
    $actor = $role === 'its requester' ? $requester : User::factory()->admin()->create();

    $this->actingAs($actor)
        ->postJson(route('tickets.comments.store', $ticket), ['body' => 'Any update on this?'])
        ->assertCreated()
        ->assertJsonPath('comment.body', 'Any update on this?')
        ->assertJsonPath('comment.author', $actor->name)
        ->assertJsonPath('comment.author_position', $actor->usertype->type_name)
        ->assertJsonPath('comment.author_is_admin', $actor->isAdmin())
        ->assertJsonPath('comment.is_mine', true)
        ->assertJsonPath('comment.sent_on', 'Today')
        ->assertJsonPath('comment.can.delete', true);

    $comment = TicketComment::sole();

    expect($comment->ticket_id)->toBe($ticket->id);
    expect($comment->user_id)->toBe($actor->id);
})->with(['its requester', 'an admin']);

test('a message cannot be empty', function () {
    $requester = User::factory()->create();
    $ticket = Ticket::factory()->for($requester, 'requester')->create();

    $this->actingAs($requester)
        ->postJson(route('tickets.comments.store', $ticket), ['body' => ''])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['body' => 'The message field is required.']);

    expect(TicketComment::count())->toBe(0);
});

test('a user cannot join the conversation on a ticket raised by someone else', function () {
    $ticket = Ticket::factory()->create();

    $this->actingAs(User::factory()->create())
        ->postJson(route('tickets.comments.store', $ticket), ['body' => 'Let me in'])
        ->assertForbidden();

    expect(TicketComment::count())->toBe(0);
});

test('nobody can send a message on a closed ticket', function () {
    $ticket = Ticket::factory()->closed()->create();

    $this->actingAs(User::factory()->admin()->create())
        ->postJson(route('tickets.comments.store', $ticket), ['body' => 'One more thing'])
        ->assertForbidden();

    expect(TicketComment::count())->toBe(0);
});

test('the conversation is returned oldest first and marks which messages are the viewer own', function () {
    $requester = User::factory()->create();
    $admin = User::factory()->admin()->create();
    $ticket = Ticket::factory()->for($requester, 'requester')->create();
    $question = TicketComment::factory()->for($ticket)->for($requester, 'author')->create(['created_at' => now()->subMinutes(5)]);
    $answer = TicketComment::factory()->for($ticket)->for($admin, 'author')->create();
    TicketComment::factory()->create();

    $this->actingAs($requester)
        ->getJson(route('tickets.comments.index', $ticket))
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.id', $question->id)
        ->assertJsonPath('data.0.is_mine', true)
        ->assertJsonPath('data.1.id', $answer->id)
        ->assertJsonPath('data.1.is_mine', false)
        ->assertJsonPath('data.1.author_is_admin', true)
        ->assertJsonPath('data.1.can.delete', false)
        ->assertJsonPath('can_comment', true);
});

test('an open page can ask only for the messages it has not seen yet', function () {
    $requester = User::factory()->create();
    $ticket = Ticket::factory()->for($requester, 'requester')->create();
    $seen = TicketComment::factory()->for($ticket)->create();
    $new = TicketComment::factory()->for($ticket)->create();

    $this->actingAs($requester)
        ->getJson(route('tickets.comments.index', ['ticket' => $ticket, 'after' => $seen->id]))
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $new->id);
});

test('an open page learns that the ticket was closed and messages are no longer accepted', function () {
    $requester = User::factory()->create();
    $ticket = Ticket::factory()->for($requester, 'requester')->closed()->create();

    $this->actingAs($requester)
        ->getJson(route('tickets.comments.index', $ticket))
        ->assertJsonPath('can_comment', false);
});

test('a user cannot read the conversation on a ticket raised by someone else', function () {
    $ticket = Ticket::factory()->create();
    TicketComment::factory()->for($ticket)->create();

    $this->actingAs(User::factory()->create())
        ->getJson(route('tickets.comments.index', $ticket))
        ->assertForbidden();
});

test(':role can delete a message', function (string $role) {
    $author = User::factory()->create();
    $comment = TicketComment::factory()->for($author, 'author')->create();
    $actor = $role === 'its author' ? $author : User::factory()->admin()->create();

    $this->actingAs($actor)
        ->deleteJson(route('ticket-comments.destroy', $comment))
        ->assertOk()
        ->assertExactJson(['id' => $comment->id]);

    expect(TicketComment::count())->toBe(0);
})->with(['its author', 'an admin']);

test('a user cannot delete a message written by someone else', function () {
    $comment = TicketComment::factory()->create();

    $this->actingAs(User::factory()->create())
        ->deleteJson(route('ticket-comments.destroy', $comment))
        ->assertForbidden();

    expect(TicketComment::count())->toBe(1);
});
