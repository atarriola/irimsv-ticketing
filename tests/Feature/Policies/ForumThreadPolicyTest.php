<?php

use App\Models\ForumThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('any user can browse the forum and start threads', function (string $ability) {
    $user = User::factory()->create();

    expect($user->can($ability, ForumThread::class))->toBeTrue();
})->with(['viewAny', 'create']);

test('any user can :ability an open thread started by someone else', function (string $ability) {
    $user = User::factory()->create();
    $thread = ForumThread::factory()->create();

    expect($user->can($ability, $thread))->toBeTrue();
})->with(['view', 'reply']);

test('an author can :ability their own thread', function (string $ability) {
    $author = User::factory()->create();
    $thread = ForumThread::factory()->for($author, 'author')->create();

    expect($author->can($ability, $thread))->toBeTrue();
})->with(['update', 'delete']);

test('a user cannot :ability a thread started by someone else', function (string $ability) {
    $otherUser = User::factory()->create();
    $thread = ForumThread::factory()->create();

    expect($otherUser->can($ability, $thread))->toBeFalse();
})->with(['update', 'delete', 'moderate']);

test('an author cannot moderate their own thread', function () {
    $author = User::factory()->create();
    $thread = ForumThread::factory()->for($author, 'author')->create();

    expect($author->can('moderate', $thread))->toBeFalse();
});

test('a locked thread rejects edits and replies from its author', function (string $ability) {
    $author = User::factory()->create();
    $thread = ForumThread::factory()->for($author, 'author')->locked()->create();

    expect($author->can($ability, $thread))->toBeFalse();
})->with(['update', 'reply']);

test('an admin can :ability a locked thread started by someone else', function (string $ability) {
    $admin = User::factory()->admin()->create();
    $thread = ForumThread::factory()->locked()->create();

    expect($admin->can($ability, $thread))->toBeTrue();
})->with(['update', 'delete', 'moderate', 'reply']);
