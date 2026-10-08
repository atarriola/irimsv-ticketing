<?php

use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('an author can :ability their own comment', function (string $ability) {
    $author = User::factory()->create();
    $comment = TicketComment::factory()->for(Ticket::factory()->for($author, 'requester'))->for($author, 'author')->create();

    expect($author->can($ability, $comment))->toBeTrue();
})->with(['update', 'delete']);

test('a user cannot :ability a comment written by someone else', function (string $ability) {
    $otherUser = User::factory()->create();
    $comment = TicketComment::factory()->for(Ticket::factory()->shared())->create();

    expect($otherUser->can($ability, $comment))->toBeFalse();
})->with(['update', 'delete']);

test('an admin can delete but not edit a comment written by someone else', function () {
    $admin = User::factory()->admin()->create();
    $comment = TicketComment::factory()->create();

    expect($admin->can('delete', $comment))->toBeTrue();
    expect($admin->can('update', $comment))->toBeFalse();
});

test('an author cannot edit their comment once the ticket is closed', function () {
    $author = User::factory()->create();
    $comment = TicketComment::factory()->for(Ticket::factory()->for($author, 'requester')->closed())->for($author, 'author')->create();

    expect($author->can('update', $comment))->toBeFalse();
});

test('only administrators can see an internal note', function () {
    $requester = User::factory()->create();
    $note = TicketComment::factory()->for(Ticket::factory()->for($requester, 'requester'))->create(['is_internal' => true]);

    expect($requester->can('view', $note))->toBeFalse();
    expect(User::factory()->admin()->create()->can('view', $note))->toBeTrue();
});
