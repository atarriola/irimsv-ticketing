<?php

use App\Enums\ForumThreadType;
use App\Models\ForumReply;
use App\Models\ForumThread;
use App\Models\ForumTopic;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/**
 * Build a valid thread payload for the given topic, optionally overriding fields.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function threadPayload(ForumTopic $topic, array $overrides = []): array
{
    return [
        'forum_topic_id' => $topic->id,
        'type' => 'query',
        'body' => 'How do I reset my password? I cannot find the option anywhere.',
        ...$overrides,
    ];
}

test('a user can post a thread from the feed and returns to the feed', function () {
    $user = User::factory()->create();
    $topic = ForumTopic::factory()->create();

    $response = $this->actingAs($user)->post(route('forum.threads.store'), threadPayload($topic));

    $response
        ->assertRedirect(route('forum.index'))
        ->assertInertiaFlash('toast.type', 'success');

    $thread = ForumThread::sole();

    expect($thread->user_id)->toBe($user->id);
    expect($thread->forum_topic_id)->toBe($topic->id);
    expect($thread->type)->toBe(ForumThreadType::Query);
    expect($thread->body)->toBe('How do I reset my password? I cannot find the option anywhere.');
    expect($thread->last_activity_at)->not->toBeNull();
});

test('a user cannot pin, lock or reassign a thread while starting it', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    $this->actingAs($user)->post(route('forum.threads.store'), threadPayload(ForumTopic::factory()->create(), [
        'is_pinned' => true,
        'is_locked' => true,
        'user_id' => $otherUser->id,
    ]));

    $thread = ForumThread::sole();

    expect($thread->is_pinned)->toBeFalse();
    expect($thread->is_locked)->toBeFalse();
    expect($thread->user_id)->toBe($user->id);
});

test('starting a thread requires a topic, type and message', function () {
    $response = $this->actingAs(User::factory()->create())->post(route('forum.threads.store'), []);

    $response->assertSessionHasErrors([
        'forum_topic_id' => 'The topic field is required.',
        'type' => 'The type field is required.',
        'body' => 'The message field is required.',
    ]);
    expect(ForumThread::count())->toBe(0);
});

test('starting a thread rejects :field with an invalid value', function (string $field, mixed $value, string $message) {
    $response = $this->actingAs(User::factory()->create())
        ->post(route('forum.threads.store'), threadPayload(ForumTopic::factory()->create(), [$field => $value]));

    $response->assertSessionHasErrors([$field => $message]);
    expect(ForumThread::count())->toBe(0);
})->with([
    'a missing topic' => ['forum_topic_id', 999, 'The selected topic is invalid.'],
    'an unknown type' => ['type', 'rant', 'The selected type is invalid.'],
    'an overlong message' => ['body', str_repeat('a', 5001), 'The message field must not be greater than 5000 characters.'],
]);

test('a thread page shows the thread, its comments in order with their replies and what the viewer may do', function () {
    $author = User::factory()->create();
    $thread = ForumThread::factory()->for($author, 'author')->create(['body' => 'Portal is slow']);
    $firstComment = ForumReply::factory()->for($thread, 'thread')->create(['created_at' => now()->subHour()]);
    $nested = ForumReply::factory()->for($thread, 'thread')->create(['parent_id' => $firstComment->id]);
    $ownComment = ForumReply::factory()->for($thread, 'thread')->for($author, 'author')->create();

    $this->actingAs($author)
        ->get(route('forum.threads.show', $thread))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Forum/Thread')
            ->where('thread.body', 'Portal is slow')
            ->where('thread.excerpt', 'Portal is slow')
            ->where('thread.replies_count', 3)
            ->where('thread.comments_count', 2)
            ->where('thread.topic.slug', $thread->topic->slug)
            ->where('thread.can.reply', true)
            ->has('comments', 2)
            ->where('comments.0.id', $firstComment->id)
            ->where('comments.0.can.delete', false)
            ->where('comments.0.children.0.id', $nested->id)
            ->where('comments.1.id', $ownComment->id)
            ->where('comments.1.can.delete', true)
            ->where('nextCommentsPage', null)
            ->where('can.moderate', false)
            ->where('can.delete', true));
});

test('a thread page offers a second page once there are more than twenty comments', function () {
    $thread = ForumThread::factory()->create();
    ForumReply::factory(21)->for($thread, 'thread')->create();

    $this->actingAs(User::factory()->create())
        ->get(route('forum.threads.show', $thread))
        ->assertInertia(fn (Assert $page) => $page
            ->has('comments', 20)
            ->where('nextCommentsPage', 2));
});

test('a locked thread tells a regular user they cannot comment', function () {
    $thread = ForumThread::factory()->locked()->create();

    $this->actingAs(User::factory()->create())
        ->get(route('forum.threads.show', $thread))
        ->assertInertia(fn (Assert $page) => $page
            ->where('thread.is_locked', true)
            ->where('thread.can.reply', false));
});

test('a thread address that is not a number is not found', function () {
    $this->actingAs(User::factory()->create())
        ->get('/forum/threads/not-a-number')
        ->assertNotFound();
});

test('an author can delete their thread and its replies go with it', function () {
    $author = User::factory()->create();
    $thread = ForumThread::factory()->for($author, 'author')->create();
    ForumReply::factory(2)->for($thread, 'thread')->create();

    $response = $this->actingAs($author)->delete(route('forum.threads.destroy', $thread));

    $response->assertRedirect(route('forum.index'));
    expect(ForumThread::count())->toBe(0);
    expect(ForumReply::count())->toBe(0);
});

test('a user cannot delete a thread started by someone else', function () {
    $thread = ForumThread::factory()->create();

    $this->actingAs(User::factory()->create())
        ->delete(route('forum.threads.destroy', $thread))
        ->assertForbidden();

    expect(ForumThread::count())->toBe(1);
});

test('an admin can pin and lock a thread', function () {
    $thread = ForumThread::factory()->create();

    $this->actingAs(User::factory()->admin()->create())
        ->patch(route('forum.threads.moderation.update', $thread), ['is_pinned' => true, 'is_locked' => true])
        ->assertRedirect();

    expect($thread->fresh()->is_pinned)->toBeTrue();
    expect($thread->fresh()->is_locked)->toBeTrue();
});

test('changing one moderation flag leaves the other untouched', function () {
    $thread = ForumThread::factory()->pinned()->create();

    $this->actingAs(User::factory()->admin()->create())
        ->patch(route('forum.threads.moderation.update', $thread), ['is_locked' => true]);

    expect($thread->fresh()->is_pinned)->toBeTrue();
    expect($thread->fresh()->is_locked)->toBeTrue();
});

test('a thread author cannot pin or lock their own thread', function () {
    $author = User::factory()->create();
    $thread = ForumThread::factory()->for($author, 'author')->create();

    $this->actingAs($author)
        ->patch(route('forum.threads.moderation.update', $thread), ['is_pinned' => true])
        ->assertForbidden();

    expect($thread->fresh()->is_pinned)->toBeFalse();
});

test('a thread page tells the client the server time it was rendered at, so live updates can start from there', function () {
    $this->freezeSecond();
    $thread = ForumThread::factory()->create();

    $this->actingAs(User::factory()->create())
        ->get(route('forum.threads.show', $thread))
        ->assertInertia(fn (Assert $page) => $page->where('syncedAt', now()->toIso8601String()));
});
