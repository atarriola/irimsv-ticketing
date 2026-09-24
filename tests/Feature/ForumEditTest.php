<?php

use App\Enums\ForumThreadType;
use App\Models\ForumReply;
use App\Models\ForumThread;
use App\Models\ForumTopic;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('an author can open the edit form for their thread', function () {
    $author = User::factory()->create();
    $thread = ForumThread::factory()->for($author, 'author')->create(['body' => 'Original message']);

    $this->actingAs($author)
        ->get(route('forum.threads.edit', $thread))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Forum/EditThread')
            ->where('thread.id', $thread->id)
            ->where('thread.body', 'Original message')
            ->has('topics', 1)
            ->has('types', 2));
});

test('an author can update their thread and move it to another topic', function () {
    $author = User::factory()->create();
    $thread = ForumThread::factory()->for($author, 'author')->create(['type' => ForumThreadType::Concern]);
    $newTopic = ForumTopic::factory()->create();

    $response = $this->actingAs($author)->put(route('forum.threads.update', $thread), [
        'forum_topic_id' => $newTopic->id,
        'type' => 'query',
        'body' => 'Updated message',
        'is_pinned' => true,
    ]);

    $response->assertRedirect(route('forum.threads.show', $thread));

    $thread->refresh();

    expect($thread->forum_topic_id)->toBe($newTopic->id);
    expect($thread->type)->toBe(ForumThreadType::Query);
    expect($thread->body)->toBe('Updated message');
    expect($thread->is_pinned)->toBeFalse();
});

test('updating a thread validates its content', function () {
    $author = User::factory()->create();
    $thread = ForumThread::factory()->for($author, 'author')->create(['body' => 'Original message']);

    $this->actingAs($author)
        ->put(route('forum.threads.update', $thread), ['forum_topic_id' => $thread->forum_topic_id, 'type' => 'query', 'body' => ''])
        ->assertSessionHasErrors(['body' => 'The message field is required.']);

    expect($thread->fresh()->body)->toBe('Original message');
});

test('a user cannot edit a thread started by someone else', function (string $method, string $routeName) {
    $thread = ForumThread::factory()->create(['body' => 'Original message']);

    $this->actingAs(User::factory()->create())
        ->{$method}(route($routeName, $thread), ['forum_topic_id' => $thread->forum_topic_id, 'type' => 'query', 'body' => 'Hijacked'])
        ->assertForbidden();

    expect($thread->fresh()->body)->toBe('Original message');
})->with([
    'form' => ['get', 'forum.threads.edit'],
    'submission' => ['put', 'forum.threads.update'],
]);

test('an author cannot edit their thread once it is locked', function () {
    $author = User::factory()->create();
    $thread = ForumThread::factory()->for($author, 'author')->locked()->create();

    $this->actingAs($author)->get(route('forum.threads.edit', $thread))->assertForbidden();
});

test('an author can update their reply and returns to the thread', function () {
    $author = User::factory()->create();
    $reply = ForumReply::factory()->for($author, 'author')->create();

    $this->actingAs($author)
        ->get(route('forum.replies.edit', $reply))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Forum/EditReply')
            ->where('reply.id', $reply->id)
            ->where('thread.id', $reply->forum_thread_id));

    $this->actingAs($author)
        ->put(route('forum.replies.update', $reply), ['body' => 'Corrected reply'])
        ->assertRedirect(route('forum.threads.show', $reply->forum_thread_id));

    expect($reply->fresh()->body)->toBe('Corrected reply');
});

test('an updated reply cannot be empty', function () {
    $author = User::factory()->create();
    $reply = ForumReply::factory()->for($author, 'author')->create(['body' => 'Original reply']);

    $this->actingAs($author)
        ->put(route('forum.replies.update', $reply), ['body' => ''])
        ->assertSessionHasErrors(['body' => 'The comment field is required.']);

    expect($reply->fresh()->body)->toBe('Original reply');
});

test('nobody but its author can edit a reply, not even an admin', function (string $method, string $routeName) {
    $reply = ForumReply::factory()->create(['body' => 'Original reply']);

    $this->actingAs(User::factory()->admin()->create())
        ->{$method}(route($routeName, $reply), ['body' => 'Rewritten by admin'])
        ->assertForbidden();

    expect($reply->fresh()->body)->toBe('Original reply');
})->with([
    'form' => ['get', 'forum.replies.edit'],
    'submission' => ['put', 'forum.replies.update'],
]);
