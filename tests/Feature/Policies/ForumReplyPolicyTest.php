<?php

use App\Models\ForumReply;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('an author can :ability their own reply', function (string $ability) {
    $author = User::factory()->create();
    $reply = ForumReply::factory()->for($author, 'author')->create();

    expect($author->can($ability, $reply))->toBeTrue();
})->with(['update', 'delete']);

test('a user cannot :ability a reply written by someone else', function (string $ability) {
    $otherUser = User::factory()->create();
    $reply = ForumReply::factory()->create();

    expect($otherUser->can($ability, $reply))->toBeFalse();
})->with(['update', 'delete']);

test('an admin can delete but not edit a reply written by someone else', function () {
    $admin = User::factory()->admin()->create();
    $reply = ForumReply::factory()->create();

    expect($admin->can('delete', $reply))->toBeTrue();
    expect($admin->can('update', $reply))->toBeFalse();
});
