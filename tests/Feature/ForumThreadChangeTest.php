<?php

use App\Enums\ReactionType;
use App\Models\ForumReaction;
use App\Models\ForumReply;
use App\Models\ForumThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('a guest cannot ask what changed in a conversation', function () {
    $thread = ForumThread::factory()->create();

    $this->getJson(route('forum.threads.changes.index', $thread))->assertUnauthorized();
});

test('an open thread receives the comments and replies posted after the last one it has, one level deep', function () {
    $this->freezeSecond();
    $thread = ForumThread::factory()->create();
    $seen = ForumReply::factory()->for($thread, 'thread')->create();
    $newReply = ForumReply::factory()->for($thread, 'thread')->create(['parent_id' => $seen->id, 'body' => 'A new reply']);
    $newComment = ForumReply::factory()->for($thread, 'thread')->create(['body' => 'A new comment']);

    $this->actingAs(User::factory()->create())
        ->getJson(route('forum.threads.changes.index', ['thread' => $thread, 'after' => $seen->id, 'known' => $seen->id]))
        ->assertOk()
        ->assertJsonPath('synced_at', now()->toIso8601String())
        ->assertJsonCount(2, 'created')
        ->assertJsonPath('created.0.id', $newReply->id)
        ->assertJsonPath('created.0.parent_id', $seen->id)
        ->assertJsonPath('created.0.body', 'A new reply')
        ->assertJsonPath('created.0.children', [])
        ->assertJsonPath('created.1.id', $newComment->id)
        ->assertJsonPath('created.1.parent_id', null)
        ->assertJsonPath('updated', [])
        ->assertJsonPath('deleted', [])
        ->assertJsonPath('replies_count', 3)
        ->assertJsonPath('comments_count', 2);
});

test('an open thread receives the comments it shows that were edited since it last asked', function () {
    $thread = ForumThread::factory()->create();
    $edited = ForumReply::factory()->for($thread, 'thread')->create(['body' => 'Edited since', 'updated_at' => now()]);
    $untouched = ForumReply::factory()->for($thread, 'thread')->create(['updated_at' => now()->subHour()]);
    $editedButNotShown = ForumReply::factory()->for($thread, 'thread')->create(['updated_at' => now()]);

    $this->actingAs(User::factory()->create())
        ->getJson(route('forum.threads.changes.index', [
            'thread' => $thread,
            'after' => $editedButNotShown->id,
            'since' => now()->subMinute()->toIso8601String(),
            'known' => "{$edited->id},{$untouched->id}",
        ]))
        ->assertOk()
        ->assertJsonCount(1, 'updated')
        ->assertJsonPath('updated.0.id', $edited->id)
        ->assertJsonPath('updated.0.body', 'Edited since')
        ->assertJsonPath('created', []);
});

test('an open thread learns which of the comments it shows have been deleted', function () {
    $thread = ForumThread::factory()->create();
    $kept = ForumReply::factory()->for($thread, 'thread')->create();
    $deleted = ForumReply::factory()->for($thread, 'thread')->create();
    $deleted->delete();

    $this->actingAs(User::factory()->create())
        ->getJson(route('forum.threads.changes.index', ['thread' => $thread, 'after' => $deleted->id, 'known' => "{$kept->id},{$deleted->id}"]))
        ->assertOk()
        ->assertJsonPath('deleted', [$deleted->id])
        ->assertJsonPath('replies_count', 1);
});

test('an open thread receives fresh reaction counts for the thread and the comments it shows', function () {
    $user = User::factory()->create();
    $thread = ForumThread::factory()->create();
    $comment = ForumReply::factory()->for($thread, 'thread')->create();
    ForumReaction::factory()->for($thread, 'thread')->for($user)->create(['type' => ReactionType::Heart]);
    ForumReaction::factory()->for($comment, 'reply')->create(['forum_thread_id' => null, 'type' => ReactionType::Like]);
    ForumReaction::factory()->for($comment, 'reply')->for($user)->create(['forum_thread_id' => null, 'type' => ReactionType::Wow]);

    $this->actingAs($user)
        ->getJson(route('forum.threads.changes.index', ['thread' => $thread, 'after' => $comment->id, 'known' => $comment->id]))
        ->assertOk()
        ->assertJsonPath('thread.reactions', ['counts' => ['heart' => 1], 'mine' => 'heart'])
        ->assertJsonPath("reactions.{$comment->id}.counts.like", 1)
        ->assertJsonPath("reactions.{$comment->id}.counts.wow", 1)
        ->assertJsonPath("reactions.{$comment->id}.mine", 'wow');
});

test('an open thread learns that it was locked and that the viewer may no longer reply', function () {
    $thread = ForumThread::factory()->locked()->create();

    $this->actingAs(User::factory()->create())
        ->getJson(route('forum.threads.changes.index', $thread))
        ->assertOk()
        ->assertJsonPath('thread.is_locked', true)
        ->assertJsonPath('thread.can.reply', false);
});

test('an open thread receives the new message when the thread itself was edited since it last asked', function () {
    $thread = ForumThread::factory()->create(['body' => 'Corrected message', 'updated_at' => now()]);

    $this->actingAs(User::factory()->create())
        ->getJson(route('forum.threads.changes.index', ['thread' => $thread, 'since' => now()->subMinute()->toIso8601String()]))
        ->assertOk()
        ->assertJsonPath('thread.body', 'Corrected message')
        ->assertJsonPath('thread.excerpt', 'Corrected message');
});

test('an open thread is not sent the message again when the thread was not edited', function () {
    $thread = ForumThread::factory()->create(['updated_at' => now()->subHour()]);

    $this->actingAs(User::factory()->create())
        ->getJson(route('forum.threads.changes.index', ['thread' => $thread, 'since' => now()->subMinute()->toIso8601String()]))
        ->assertOk()
        ->assertJsonMissingPath('thread.body');
});

test('an open thread is not told about replies that belong to another thread', function () {
    $thread = ForumThread::factory()->create();
    $elsewhere = ForumReply::factory()->create(['updated_at' => now()]);
    ForumReaction::factory()->for($elsewhere, 'reply')->create(['forum_thread_id' => null]);

    $this->actingAs(User::factory()->create())
        ->getJson(route('forum.threads.changes.index', [
            'thread' => $thread,
            'since' => now()->subMinute()->toIso8601String(),
            'known' => $elsewhere->id,
        ]))
        ->assertOk()
        ->assertJsonPath('created', [])
        ->assertJsonPath('updated', [])
        ->assertJsonMissingPath("reactions.{$elsewhere->id}");
});

test('an open thread cannot ask about changes with an invalid :field', function (string $field, mixed $value) {
    $thread = ForumThread::factory()->create();

    $this->actingAs(User::factory()->create())
        ->getJson(route('forum.threads.changes.index', ['thread' => $thread, $field => $value]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);
})->with([
    'a negative after' => ['after', -1],
    'a since that is not a date' => ['since', 'not-a-date'],
    'a known list that is not ids' => ['known', '1,abc'],
]);
