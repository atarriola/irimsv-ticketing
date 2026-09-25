<?php

use App\Models\ForumReply;
use App\Models\ForumThread;
use App\Models\ForumTopic;
use App\Models\User;
use App\Notifications\ForumCommentReplied;
use App\Notifications\ForumThreadCommented;
use App\Notifications\ForumThreadStarted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('a comment notifies the thread author but not the person who wrote it', function () {
    Notification::fake();
    $author = User::factory()->create();
    $thread = ForumThread::factory()->for($author, 'author')->create();
    $commenter = User::factory()->create();

    $this->actingAs($commenter)
        ->postJson(route('forum.threads.replies.store', $thread), ['body' => 'Have you tried clearing the cache?'])
        ->assertCreated();

    Notification::assertSentTo($author, ForumThreadCommented::class, fn (ForumThreadCommented $notification): bool => $notification->thread->is($thread) && $notification->reply->body === 'Have you tried clearing the cache?');
    Notification::assertNotSentTo($commenter, ForumThreadCommented::class);
});

test('commenting on your own thread notifies nobody', function () {
    Notification::fake();
    $author = User::factory()->create();
    $thread = ForumThread::factory()->for($author, 'author')->create();

    $this->actingAs($author)
        ->postJson(route('forum.threads.replies.store', $thread), ['body' => 'Update: fixed.'])
        ->assertCreated();

    Notification::assertNothingSent();
});

test('a reply notifies the comment author and the thread author, each once', function () {
    Notification::fake();
    $author = User::factory()->create();
    $thread = ForumThread::factory()->for($author, 'author')->create();
    $commenter = User::factory()->create();
    $comment = ForumReply::factory()->for($thread, 'thread')->for($commenter, 'author')->create();
    $replier = User::factory()->create();

    $this->actingAs($replier)
        ->postJson(route('forum.threads.replies.store', $thread), ['body' => 'I agree.', 'parent_id' => $comment->id])
        ->assertCreated();

    Notification::assertSentTo($commenter, ForumCommentReplied::class, fn (ForumCommentReplied $notification): bool => $notification->reply->body === 'I agree.');
    Notification::assertSentTo($author, ForumThreadCommented::class);
    Notification::assertNotSentTo($commenter, ForumThreadCommented::class);
    Notification::assertNotSentTo($author, ForumCommentReplied::class);
    Notification::assertCount(2);
});

test('replying to your own comment on someone else\'s thread notifies only the thread author', function () {
    Notification::fake();
    $author = User::factory()->create();
    $thread = ForumThread::factory()->for($author, 'author')->create();
    $commenter = User::factory()->create();
    $comment = ForumReply::factory()->for($thread, 'thread')->for($commenter, 'author')->create();

    $this->actingAs($commenter)
        ->postJson(route('forum.threads.replies.store', $thread), ['body' => 'Adding to my earlier point.', 'parent_id' => $comment->id])
        ->assertCreated();

    Notification::assertSentTo($author, ForumThreadCommented::class);
    Notification::assertCount(1);
});

test('a new thread notifies the helpdesk administrators except the one who started it', function () {
    Notification::fake();
    $author = User::factory()->admin()->create();
    $administrator = User::factory()->admin()->create();
    $teacher = User::factory()->create();
    $topic = ForumTopic::factory()->create();

    $this->actingAs($author)
        ->post(route('forum.threads.store'), ['forum_topic_id' => $topic->id, 'type' => 'query', 'body' => 'Is the portal down?'])
        ->assertRedirect();

    Notification::assertSentTo($administrator, ForumThreadStarted::class, fn (ForumThreadStarted $notification): bool => $notification->thread->body === 'Is the portal down?');
    Notification::assertNotSentTo([$author, $teacher], ForumThreadStarted::class);
});

test('a stored notification carries what the bell shows', function () {
    $author = User::factory()->create(['firstname' => 'Ana', 'lastname' => 'Reyes']);
    $thread = ForumThread::factory()->for($author, 'author')->create(['body' => 'Portal is slow']);
    $commenter = User::factory()->create(['firstname' => 'Maria', 'lastname' => 'Santos']);
    $this->actingAs($commenter)->postJson(route('forum.threads.replies.store', $thread), ['body' => 'Same here, since this morning.'])->assertCreated();

    $this->actingAs($author)
        ->getJson(route('notifications.index'))
        ->assertOk()
        ->assertJsonPath('unread_count', 1)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.kind', 'comment')
        ->assertJsonPath('data.0.message', 'Maria Santos commented on your thread')
        ->assertJsonPath('data.0.excerpt', 'Same here, since this morning.')
        ->assertJsonPath('data.0.url', route('forum.threads.show', $thread))
        ->assertJsonPath('data.0.is_read', false);
});

test('every page shares how many notifications are unread', function () {
    $user = User::factory()->create();
    $thread = ForumThread::factory()->for($user, 'author')->create();
    $reply = ForumReply::factory()->for($thread, 'thread')->create()->load('author');
    $user->notify(new ForumThreadCommented($thread, $reply));

    $this->actingAs($user)
        ->get(route('forum.index'))
        ->assertInertia(fn (Assert $page) => $page->where('unreadNotifications', 1));
});

test('a user can mark all of their notifications as read', function () {
    $user = User::factory()->create();
    $thread = ForumThread::factory()->for($user, 'author')->create();
    $reply = ForumReply::factory()->for($thread, 'thread')->create()->load('author');
    $user->notify(new ForumThreadCommented($thread, $reply));
    $user->notify(new ForumCommentReplied($thread, $reply));

    $this->actingAs($user)
        ->postJson(route('notifications.read.store'))
        ->assertOk()
        ->assertJsonPath('unread_count', 0);

    expect($user->unreadNotifications()->count())->toBe(0);
    expect($user->notifications()->count())->toBe(2);
});

test('a user can mark one notification as read and is told how many remain', function () {
    $user = User::factory()->create();
    $thread = ForumThread::factory()->for($user, 'author')->create();
    $reply = ForumReply::factory()->for($thread, 'thread')->create()->load('author');
    $user->notify(new ForumThreadCommented($thread, $reply));
    $user->notify(new ForumCommentReplied($thread, $reply));
    $notification = $user->notifications()->latest()->first();

    $this->actingAs($user)
        ->putJson(route('notifications.read.update', $notification->id))
        ->assertOk()
        ->assertJsonPath('unread_count', 1);

    expect($notification->fresh()->read_at)->not->toBeNull();
});

test('a user cannot mark someone else\'s notification as read', function () {
    $owner = User::factory()->create();
    $thread = ForumThread::factory()->for($owner, 'author')->create();
    $reply = ForumReply::factory()->for($thread, 'thread')->create()->load('author');
    $owner->notify(new ForumThreadCommented($thread, $reply));
    $notification = $owner->notifications()->first();

    $this->actingAs(User::factory()->create())
        ->putJson(route('notifications.read.update', $notification->id))
        ->assertNotFound();

    expect($notification->fresh()->read_at)->toBeNull();
});

test('the bell can ask for only the unread notifications', function () {
    $user = User::factory()->create();
    $thread = ForumThread::factory()->for($user, 'author')->create();
    $reply = ForumReply::factory()->for($thread, 'thread')->create()->load('author');
    $user->notify(new ForumThreadCommented($thread, $reply));
    $user->notify(new ForumCommentReplied($thread, $reply));
    $user->notifications()->where('type', ForumCommentReplied::class)->first()->markAsRead();

    $this->actingAs($user)
        ->getJson(route('notifications.index', ['filter' => 'unread']))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.kind', 'comment')
        ->assertJsonPath('unread_count', 1);
});

test('the notifications page lists every notification', function () {
    $user = User::factory()->create();
    $thread = ForumThread::factory()->for($user, 'author')->create();
    $reply = ForumReply::factory()->for($thread, 'thread')->create()->load('author');
    $user->notify(new ForumThreadCommented($thread, $reply));
    $user->notify(new ForumCommentReplied($thread, $reply));

    $this->actingAs($user)
        ->get(route('notifications.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Notifications/Index')
            ->where('filter', 'all')
            ->has('notifications.data', 2)
            ->where('unreadNotifications', 2));
});

test('the notifications page can be narrowed to the unread ones', function () {
    $user = User::factory()->create();
    $thread = ForumThread::factory()->for($user, 'author')->create();
    $reply = ForumReply::factory()->for($thread, 'thread')->create()->load('author');
    $user->notify(new ForumThreadCommented($thread, $reply));
    $user->notify(new ForumCommentReplied($thread, $reply));
    $user->notifications()->where('type', ForumCommentReplied::class)->first()->markAsRead();

    $this->actingAs($user)
        ->get(route('notifications.index', ['filter' => 'unread']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('filter', 'unread')
            ->has('notifications.data', 1)
            ->where('notifications.data.0.kind', 'comment')
            ->where('notifications.data.0.is_read', false));
});

test('a guest cannot read notifications', function () {
    $this->getJson(route('notifications.index'))->assertUnauthorized();
});

test('a guest is sent to the login page when opening the notifications page', function () {
    $this->get(route('notifications.index'))->assertRedirect(route('login'));
});
