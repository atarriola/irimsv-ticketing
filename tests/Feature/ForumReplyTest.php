<?php

use App\Models\ForumReply;
use App\Models\ForumThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('a guest cannot comment', function () {
    $thread = ForumThread::factory()->create();

    $this->postJson(route('forum.threads.replies.store', $thread), ['body' => 'Hello'])->assertUnauthorized();

    expect(ForumReply::count())->toBe(0);
});

test('a user can comment on a thread and the thread becomes the most recently active', function () {
    $user = User::factory()->create(['name' => 'Maria Santos']);
    $thread = ForumThread::factory()->create(['last_activity_at' => now()->subWeek()]);

    $this->actingAs($user)
        ->postJson(route('forum.threads.replies.store', $thread), ['body' => 'Have you tried clearing the cache?'])
        ->assertCreated()
        ->assertJsonPath('reply.body', 'Have you tried clearing the cache?')
        ->assertJsonPath('reply.author', 'Maria Santos')
        ->assertJsonPath('reply.parent_id', null)
        ->assertJsonPath('reply.children', [])
        ->assertJsonPath('reply.can.delete', true)
        ->assertJsonPath('replies_count', 1)
        ->assertJsonPath('comments_count', 1);

    $reply = ForumReply::sole();

    expect($reply->user_id)->toBe($user->id);
    expect($reply->forum_thread_id)->toBe($thread->id);
    expect($thread->fresh()->last_activity_at->isAfter(now()->subMinute()))->toBeTrue();
});

test('a user can reply to a comment', function () {
    $thread = ForumThread::factory()->create();
    $comment = ForumReply::factory()->for($thread, 'thread')->create();

    $this->actingAs(User::factory()->create())
        ->postJson(route('forum.threads.replies.store', $thread), ['body' => 'I agree with this.', 'parent_id' => $comment->id])
        ->assertCreated()
        ->assertJsonPath('reply.parent_id', $comment->id)
        ->assertJsonPath('replies_count', 2)
        ->assertJsonPath('comments_count', 1);

    expect($comment->children()->sole()->body)->toBe('I agree with this.');
});

test('replying to a reply keeps the conversation one level deep', function () {
    $thread = ForumThread::factory()->create();
    $comment = ForumReply::factory()->for($thread, 'thread')->create();
    $nested = ForumReply::factory()->for($thread, 'thread')->create(['parent_id' => $comment->id]);

    $this->actingAs(User::factory()->create())
        ->postJson(route('forum.threads.replies.store', $thread), ['body' => 'Answering the nested reply.', 'parent_id' => $nested->id])
        ->assertCreated()
        ->assertJsonPath('reply.parent_id', $comment->id);

    expect($comment->children()->count())->toBe(2);
    expect($nested->children()->count())->toBe(0);
});

test('a reply cannot answer a comment from another thread', function () {
    $thread = ForumThread::factory()->create();
    $elsewhere = ForumReply::factory()->create();

    $this->actingAs(User::factory()->create())
        ->postJson(route('forum.threads.replies.store', $thread), ['body' => 'Wrong place', 'parent_id' => $elsewhere->id])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['parent_id' => 'The comment you are replying to no longer exists.']);

    expect(ForumReply::count())->toBe(1);
});

test('a comment cannot be empty', function () {
    $thread = ForumThread::factory()->create();

    $this->actingAs(User::factory()->create())
        ->postJson(route('forum.threads.replies.store', $thread), ['body' => ''])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['body' => 'The comment field is required.']);

    expect(ForumReply::count())->toBe(0);
});

test('a regular user cannot comment on a locked thread', function () {
    $thread = ForumThread::factory()->locked()->create();

    $this->actingAs(User::factory()->create())
        ->postJson(route('forum.threads.replies.store', $thread), ['body' => 'Let me in'])
        ->assertForbidden();

    expect(ForumReply::count())->toBe(0);
});

test('an admin can still comment on a locked thread', function () {
    $thread = ForumThread::factory()->locked()->create();

    $this->actingAs(User::factory()->admin()->create())
        ->postJson(route('forum.threads.replies.store', $thread), ['body' => 'Closing note from the team.'])
        ->assertCreated();

    expect(ForumReply::count())->toBe(1);
});

test(':role can delete a comment and receives the new totals', function (string $role) {
    $author = User::factory()->create();
    $thread = ForumThread::factory()->create();
    $comment = ForumReply::factory()->for($thread, 'thread')->for($author, 'author')->create();
    ForumReply::factory()->for($thread, 'thread')->create();
    $actor = $role === 'its author' ? $author : User::factory()->admin()->create();

    $this->actingAs($actor)
        ->deleteJson(route('forum.replies.destroy', $comment))
        ->assertOk()
        ->assertExactJson(['replies_count' => 1, 'comments_count' => 1]);

    expect(ForumReply::whereKey($comment->id)->exists())->toBeFalse();
})->with(['its author', 'an admin']);

test('deleting a comment removes the replies under it', function () {
    $author = User::factory()->create();
    $thread = ForumThread::factory()->create();
    $comment = ForumReply::factory()->for($thread, 'thread')->for($author, 'author')->create();
    ForumReply::factory(2)->for($thread, 'thread')->create(['parent_id' => $comment->id]);

    $this->actingAs($author)
        ->deleteJson(route('forum.replies.destroy', $comment))
        ->assertExactJson(['replies_count' => 0, 'comments_count' => 0]);

    expect(ForumReply::count())->toBe(0);
});

test('a user cannot delete a comment written by someone else', function () {
    $reply = ForumReply::factory()->create();

    $this->actingAs(User::factory()->create())
        ->deleteJson(route('forum.replies.destroy', $reply))
        ->assertForbidden();

    expect(ForumReply::count())->toBe(1);
});
