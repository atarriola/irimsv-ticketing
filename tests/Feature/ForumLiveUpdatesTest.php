<?php

use App\Events\ForumThreadChanged;
use App\Events\ForumThreadPosted;
use App\Models\ForumReply;
use App\Models\ForumThread;
use App\Models\ForumTopic;
use App\Models\User;
use Illuminate\Broadcasting\BroadcastException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Exceptions;

uses(RefreshDatabase::class);

/**
 * Broadcast through Reverb's broadcaster instead of the null driver the tests run on, which neither sends
 * anything nor authorises channels. Nothing listens on port 1, so a broadcast made this way always fails.
 */
function authoriseChannelsThroughReverb(int $port = 8080): void
{
    config()->set('broadcasting.connections.reverb', [
        'driver' => 'reverb',
        'key' => 'test-key',
        'secret' => 'test-secret',
        'app_id' => 'test-app',
        'options' => ['host' => '127.0.0.1', 'port' => $port, 'scheme' => 'http', 'useTLS' => false],
        'client_options' => ['connect_timeout' => 1, 'timeout' => 2],
    ]);

    $reverb = Broadcast::driver('reverb');

    foreach (Broadcast::driver()->getChannels() as $channel => $callback) {
        $reverb->channel($channel, $callback);
    }

    Broadcast::setDefaultDriver('reverb');
}

test('a thread change is announced to the thread and to the forum feed', function () {
    $channels = array_map(fn ($channel): string => $channel->name, (new ForumThreadChanged(7))->broadcastOn());

    expect($channels)->toBe(['private-forum.thread.7', 'private-forum']);
});

test('a new thread is announced to the forum feed', function () {
    $channels = array_map(fn ($channel): string => $channel->name, (new ForumThreadPosted(7))->broadcastOn());

    expect($channels)->toBe(['private-forum']);
});

test('posting a comment tells everyone reading the thread once, even though it also bumps the thread', function () {
    $thread = ForumThread::factory()->create();
    Event::fake([ForumThreadChanged::class, ForumThreadPosted::class]);

    $this->actingAs(User::factory()->create())
        ->postJson(route('forum.threads.replies.store', $thread), ['body' => 'Hello'])
        ->assertCreated();

    Event::assertDispatchedTimes(ForumThreadChanged::class, 1);
    Event::assertDispatched(ForumThreadChanged::class, fn (ForumThreadChanged $event): bool => $event->threadId === $thread->id);
    Event::assertNotDispatched(ForumThreadPosted::class);
});

test('editing a comment tells everyone reading the thread', function () {
    $author = User::factory()->create();
    $reply = ForumReply::factory()->for($author, 'author')->create();
    Event::fake([ForumThreadChanged::class]);

    $this->actingAs($author)
        ->put(route('forum.replies.update', $reply), ['body' => 'Corrected'])
        ->assertRedirect();

    Event::assertDispatched(ForumThreadChanged::class, fn (ForumThreadChanged $event): bool => $event->threadId === $reply->forum_thread_id);
});

test('deleting a comment tells everyone reading the thread', function () {
    $author = User::factory()->create();
    $reply = ForumReply::factory()->for($author, 'author')->create();
    Event::fake([ForumThreadChanged::class]);

    $this->actingAs($author)
        ->deleteJson(route('forum.replies.destroy', $reply))
        ->assertOk();

    Event::assertDispatched(ForumThreadChanged::class, fn (ForumThreadChanged $event): bool => $event->threadId === $reply->forum_thread_id);
});

test('reacting to a comment tells everyone reading its thread', function () {
    $reply = ForumReply::factory()->create();
    Event::fake([ForumThreadChanged::class]);

    $this->actingAs(User::factory()->create())
        ->postJson(route('forum.replies.reactions.store', $reply), ['type' => 'heart'])
        ->assertOk();

    Event::assertDispatched(ForumThreadChanged::class, fn (ForumThreadChanged $event): bool => $event->threadId === $reply->forum_thread_id);
});

test('taking a reaction back from a thread tells everyone reading it', function () {
    $user = User::factory()->create();
    $thread = ForumThread::factory()->create();
    $this->actingAs($user)->postJson(route('forum.threads.reactions.store', $thread), ['type' => 'heart']);
    Event::fake([ForumThreadChanged::class]);

    $this->actingAs($user)
        ->postJson(route('forum.threads.reactions.store', $thread), ['type' => 'heart'])
        ->assertOk();

    Event::assertDispatched(ForumThreadChanged::class, fn (ForumThreadChanged $event): bool => $event->threadId === $thread->id);
});

test('locking a thread tells everyone reading it', function () {
    $admin = User::factory()->admin()->create();
    $thread = ForumThread::factory()->create();
    Event::fake([ForumThreadChanged::class]);

    $this->actingAs($admin)
        ->patch(route('forum.threads.moderation.update', $thread), ['is_locked' => true])
        ->assertRedirect();

    Event::assertDispatched(ForumThreadChanged::class, fn (ForumThreadChanged $event): bool => $event->threadId === $thread->id);
});

test('deleting a thread tells everyone reading it', function () {
    $author = User::factory()->create();
    $thread = ForumThread::factory()->for($author, 'author')->create();
    Event::fake([ForumThreadChanged::class]);

    $this->actingAs($author)
        ->delete(route('forum.threads.destroy', $thread))
        ->assertRedirect();

    Event::assertDispatched(ForumThreadChanged::class, fn (ForumThreadChanged $event): bool => $event->threadId === $thread->id);
});

test('posting a comment still succeeds when the WebSocket server cannot be reached, and the failure is reported', function () {
    Exceptions::fake();
    $user = User::factory()->create();
    $thread = ForumThread::factory()->create();
    authoriseChannelsThroughReverb(port: 1);

    $this->actingAs($user)
        ->postJson(route('forum.threads.replies.store', $thread), ['body' => 'Hello'])
        ->assertCreated();

    expect($thread->replies()->count())->toBe(1);
    Exceptions::assertReported(BroadcastException::class);
});

test('starting a thread tells everyone on the forum feed', function () {
    $user = User::factory()->create();
    $topic = ForumTopic::factory()->create();
    Event::fake([ForumThreadChanged::class, ForumThreadPosted::class]);

    $this->actingAs($user)
        ->post(route('forum.threads.store'), ['forum_topic_id' => $topic->id, 'type' => 'query', 'body' => 'Is the portal down?'])
        ->assertRedirect();

    Event::assertDispatched(ForumThreadPosted::class, fn (ForumThreadPosted $event): bool => $event->threadId === ForumThread::sole()->id);
    Event::assertNotDispatched(ForumThreadChanged::class);
});

test('a signed-in user may follow :channel live', function (string $channel) {
    $user = User::factory()->create();
    ForumThread::factory()->create();
    authoriseChannelsThroughReverb();

    $this->actingAs($user)
        ->postJson('/broadcasting/auth', ['channel_name' => $channel, 'socket_id' => '1234.5678'])
        ->assertOk()
        ->assertJsonStructure(['auth']);
})->with(['the forum feed' => 'private-forum', 'a thread' => 'private-forum.thread.1']);

test('a guest may not follow :channel live', function (string $channel) {
    ForumThread::factory()->create();
    authoriseChannelsThroughReverb();

    $this->postJson('/broadcasting/auth', ['channel_name' => $channel, 'socket_id' => '1234.5678'])
        ->assertForbidden();
})->with(['the forum feed' => 'private-forum', 'a thread' => 'private-forum.thread.1']);

test('a user may follow their own notifications live but not someone else', function () {
    $user = User::factory()->create();
    $someoneElse = User::factory()->create();
    authoriseChannelsThroughReverb();

    $this->actingAs($user)
        ->postJson('/broadcasting/auth', ['channel_name' => "private-App.Models.User.{$user->id}", 'socket_id' => '1234.5678'])
        ->assertOk()
        ->assertJsonStructure(['auth']);

    $this->actingAs($user)
        ->postJson('/broadcasting/auth', ['channel_name' => "private-App.Models.User.{$someoneElse->id}", 'socket_id' => '1234.5678'])
        ->assertForbidden();
});

test('nobody may follow a thread that does not exist', function () {
    $user = User::factory()->create();
    authoriseChannelsThroughReverb();

    $this->actingAs($user)
        ->postJson('/broadcasting/auth', ['channel_name' => 'private-forum.thread.999', 'socket_id' => '1234.5678'])
        ->assertForbidden();
});
