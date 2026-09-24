<?php

use App\Models\TicketComment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('an author can :ability their own comment', function (string $ability) {
    $author = User::factory()->create();
    $comment = TicketComment::factory()->for($author, 'author')->create();

    expect($author->can($ability, $comment))->toBeTrue();
})->with(['update', 'delete']);

test('a user cannot :ability a comment written by someone else', function (string $ability) {
    $otherUser = User::factory()->create();
    $comment = TicketComment::factory()->create();

    expect($otherUser->can($ability, $comment))->toBeFalse();
})->with(['update', 'delete']);

test('an admin can delete but not edit a comment written by someone else', function () {
    $admin = User::factory()->admin()->create();
    $comment = TicketComment::factory()->create();

    expect($admin->can('delete', $comment))->toBeTrue();
    expect($admin->can('update', $comment))->toBeFalse();
});
