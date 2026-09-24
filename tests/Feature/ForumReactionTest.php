<?php

use App\Enums\ReactionType;
use App\Models\ForumReaction;
use App\Models\ForumReply;
use App\Models\ForumThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('a guest cannot react', function () {
    $thread = ForumThread::factory()->create();

    $this->postJson(route('forum.threads.reactions.store', $thread), ['type' => 'heart'])->assertUnauthorized();

    expect(ForumReaction::count())->toBe(0);
});

test('a user can heart a thread and receives the new totals', function () {
    $user = User::factory()->create();
    $thread = ForumThread::factory()->create();
    ForumReaction::factory()->for($thread, 'thread')->create(['type' => ReactionType::Heart]);

    $this->actingAs($user)
        ->postJson(route('forum.threads.reactions.store', $thread), ['type' => 'heart'])
        ->assertOk()
        ->assertExactJson(['counts' => ['heart' => 2], 'mine' => 'heart']);

    expect($thread->reactions()->whereBelongsTo($user)->sole()->type)->toBe(ReactionType::Heart);
});

test('sending the same reaction again takes it back', function () {
    $user = User::factory()->create();
    $thread = ForumThread::factory()->create();

    $this->actingAs($user)->postJson(route('forum.threads.reactions.store', $thread), ['type' => 'heart']);

    $this->actingAs($user)
        ->postJson(route('forum.threads.reactions.store', $thread), ['type' => 'heart'])
        ->assertOk()
        ->assertExactJson(['counts' => [], 'mine' => null]);

    expect(ForumReaction::count())->toBe(0);
});

test('choosing another reaction replaces the first one', function () {
    $user = User::factory()->create();
    $thread = ForumThread::factory()->create();

    $this->actingAs($user)->postJson(route('forum.threads.reactions.store', $thread), ['type' => 'heart']);

    $this->actingAs($user)
        ->postJson(route('forum.threads.reactions.store', $thread), ['type' => 'laugh'])
        ->assertExactJson(['counts' => ['laugh' => 1], 'mine' => 'laugh']);

    expect(ForumReaction::count())->toBe(1);
});

test('a user can react to a reply', function () {
    $user = User::factory()->create();
    $reply = ForumReply::factory()->create();

    $this->actingAs($user)
        ->postJson(route('forum.replies.reactions.store', $reply), ['type' => 'like'])
        ->assertOk()
        ->assertExactJson(['counts' => ['like' => 1], 'mine' => 'like']);

    $reaction = ForumReaction::sole();

    expect($reaction->forum_reply_id)->toBe($reply->id);
    expect($reaction->forum_thread_id)->toBeNull();
});

test('an unknown reaction is rejected', function () {
    $thread = ForumThread::factory()->create();

    $this->actingAs(User::factory()->create())
        ->postJson(route('forum.threads.reactions.store', $thread), ['type' => 'angry'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['type' => 'The selected type is invalid.']);

    expect(ForumReaction::count())->toBe(0);
});

test('reactions disappear with the post or the user they belong to', function () {
    $user = User::factory()->create();
    $thread = ForumThread::factory()->create();
    $reply = ForumReply::factory()->for($thread, 'thread')->create();
    $thread->toggleReaction($user, ReactionType::Heart);
    $reply->toggleReaction($user, ReactionType::Like);
    $thread->toggleReaction(User::factory()->create(), ReactionType::Wow);

    $user->delete();
    expect(ForumReaction::count())->toBe(1);

    $thread->delete();
    expect(ForumReaction::count())->toBe(0);
});

test('the feed and the thread page show each post with its reactions and the viewer own choice', function () {
    $viewer = User::factory()->create();
    $thread = ForumThread::factory()->create();
    $reply = ForumReply::factory()->for($thread, 'thread')->create();
    $thread->toggleReaction($viewer, ReactionType::Heart);
    $thread->toggleReaction(User::factory()->create(), ReactionType::Heart);
    $reply->toggleReaction(User::factory()->create(), ReactionType::Sad);

    $this->actingAs($viewer)
        ->get(route('forum.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('reactionTypes', 5)
            ->where('reactionTypes.0.value', 'heart')
            ->where('threads.data.0.reactions.counts.heart', 2)
            ->where('threads.data.0.reactions.mine', 'heart')
            ->where('threads.data.0.preview_comments.0.reactions.counts.sad', 1)
            ->where('threads.data.0.preview_comments.0.reactions.mine', null));

    $this->actingAs($viewer)
        ->get(route('forum.threads.show', $thread))
        ->assertInertia(fn (Assert $page) => $page
            ->has('reactionTypes', 5)
            ->where('thread.reactions.mine', 'heart')
            ->where('comments.0.reactions.counts.sad', 1));
});
