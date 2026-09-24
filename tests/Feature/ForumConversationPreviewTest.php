<?php

use App\Models\ForumReply;
use App\Models\ForumThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('each thread in the feed comes with its first two comments and their replies', function () {
    $busy = ForumThread::factory()->create(['last_activity_at' => now()]);
    $first = ForumReply::factory()->for($busy, 'thread')->create(['body' => 'First comment', 'created_at' => now()->subMinutes(5)]);
    $nested = ForumReply::factory()->for($busy, 'thread')->create(['body' => 'Reply to the first', 'parent_id' => $first->id, 'created_at' => now()->subMinutes(4)]);
    $second = ForumReply::factory()->for($busy, 'thread')->create(['body' => 'Second comment', 'created_at' => now()->subMinutes(3)]);
    ForumReply::factory()->for($busy, 'thread')->create(['body' => 'Third comment', 'created_at' => now()->subMinutes(2)]);

    $quiet = ForumThread::factory()->create(['last_activity_at' => now()->subDay()]);
    $only = ForumReply::factory()->for($quiet, 'thread')->create();

    $this->actingAs(User::factory()->create())
        ->get(route('forum.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('threads.data.0.id', $busy->id)
            ->where('threads.data.0.replies_count', 4)
            ->where('threads.data.0.comments_count', 3)
            ->where('threads.data.0.can.reply', true)
            ->has('threads.data.0.preview_comments', 2)
            ->where('threads.data.0.preview_comments.0.id', $first->id)
            ->where('threads.data.0.preview_comments.0.body', 'First comment')
            ->has('threads.data.0.preview_comments.0.children', 1)
            ->where('threads.data.0.preview_comments.0.children.0.id', $nested->id)
            ->where('threads.data.0.preview_comments.0.children.0.parent_id', $first->id)
            ->where('threads.data.0.preview_comments.1.id', $second->id)
            ->where('threads.data.0.preview_comments.1.children', [])
            ->where('threads.data.1.id', $quiet->id)
            ->has('threads.data.1.preview_comments', 1)
            ->where('threads.data.1.preview_comments.0.id', $only->id));
});

test('a locked thread tells a regular user in the feed that they cannot comment', function () {
    ForumThread::factory()->locked()->create();

    $this->actingAs(User::factory()->create())
        ->get(route('forum.index'))
        ->assertInertia(fn (Assert $page) => $page->where('threads.data.0.can.reply', false));
});

test('a guest cannot load a conversation', function () {
    $thread = ForumThread::factory()->create();

    $this->getJson(route('forum.threads.replies.index', $thread))->assertUnauthorized();
});

test('expanding a conversation returns the comments in order with their replies and what the viewer may do', function () {
    $viewer = User::factory()->create();
    $thread = ForumThread::factory()->create();
    $first = ForumReply::factory()->for($thread, 'thread')->create(['created_at' => now()->subMinutes(3)]);
    $nested = ForumReply::factory()->for($thread, 'thread')->for($viewer, 'author')->create(['parent_id' => $first->id, 'body' => 'My nested reply']);
    $own = ForumReply::factory()->for($thread, 'thread')->for($viewer, 'author')->create(['body' => 'My comment']);
    ForumReply::factory()->create();

    $this->actingAs($viewer)
        ->getJson(route('forum.threads.replies.index', $thread))
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.id', $first->id)
        ->assertJsonPath('data.0.can.delete', false)
        ->assertJsonCount(1, 'data.0.children')
        ->assertJsonPath('data.0.children.0.id', $nested->id)
        ->assertJsonPath('data.0.children.0.body', 'My nested reply')
        ->assertJsonPath('data.0.children.0.can.delete', true)
        ->assertJsonPath('data.1.id', $own->id)
        ->assertJsonPath('data.1.body', 'My comment')
        ->assertJsonPath('data.1.reactions.mine', null)
        ->assertJsonPath('next_page', null);
});

test('a long conversation loads twenty comments at a time', function () {
    $thread = ForumThread::factory()->create();
    ForumReply::factory(21)->for($thread, 'thread')->create();

    $this->actingAs(User::factory()->create())
        ->getJson(route('forum.threads.replies.index', $thread))
        ->assertJsonCount(20, 'data')
        ->assertJsonPath('next_page', 2);

    $this->actingAs(User::factory()->create())
        ->getJson(route('forum.threads.replies.index', ['thread' => $thread, 'page' => 2]))
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('next_page', null);
});
