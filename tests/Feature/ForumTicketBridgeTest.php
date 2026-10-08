<?php

use App\Models\ForumReply;
use App\Models\ForumThread;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('the author of a thread can raise it as a ticket, prefilled from the thread', function () {
    $author = User::factory()->create();
    $thread = ForumThread::factory()->for($author, 'author')->create(['body' => 'The grade sheet export keeps failing for my section.']);

    $this->actingAs($author)
        ->get(route('forum.threads.show', $thread))
        ->assertInertia(fn (Assert $page) => $page->where('can.raiseTicket', true)->where('thread.tickets', []));

    $this->actingAs($author)
        ->get(route('tickets.create', ['thread' => $thread->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('thread.id', $thread->id)
            ->where('thread.body', 'The grade sheet export keeps failing for my section.'));

    $this->actingAs($author)->post(route('tickets.store'), [
        'type' => 'bug_report',
        'priority' => 'high',
        'subject' => 'Grade sheet export fails',
        'description' => 'The grade sheet export keeps failing for my section.',
        'forum_thread_id' => $thread->id,
    ]);

    $ticket = Ticket::sole();
    expect($ticket->forum_thread_id)->toBe($thread->id);

    $this->actingAs($author)
        ->get(route('tickets.show', $ticket))
        ->assertInertia(fn (Assert $page) => $page->where('ticket.forum_thread.url', route('forum.threads.show', $thread)));

    $this->actingAs($author)
        ->get(route('forum.threads.show', $thread))
        ->assertInertia(fn (Assert $page) => $page
            ->has('thread.tickets', 1)
            ->where('thread.tickets.0.key', $ticket->key)
            ->where('thread.tickets.0.url', route('tickets.show', $ticket)));
});

test('someone else cannot prefill a ticket from a thread they did not start, but an admin can', function () {
    $thread = ForumThread::factory()->create();

    $this->actingAs(User::factory()->create())
        ->get(route('tickets.create', ['thread' => $thread->id]))
        ->assertInertia(fn (Assert $page) => $page->where('thread', null));

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('tickets.create', ['thread' => $thread->id]))
        ->assertInertia(fn (Assert $page) => $page->where('thread.id', $thread->id));
});

test('a ticket cannot point at a thread that does not exist', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('tickets.store'), ['type' => 'bug_report', 'priority' => 'low', 'subject' => 'x', 'description' => 'y', 'forum_thread_id' => 999])
        ->assertSessionHasErrors('forum_thread_id');
});

test(':who can mark a comment as the answer to a thread and unmark it', function (string $who) {
    $author = User::factory()->create();
    $thread = ForumThread::factory()->for($author, 'author')->create();
    $reply = ForumReply::factory()->for($thread, 'thread')->create();
    $actor = $who === 'the author' ? $author : User::factory()->admin()->create();

    $this->actingAs($actor)
        ->patch(route('forum.threads.answer.update', $thread), ['reply_id' => $reply->id])
        ->assertRedirect()
        ->assertInertiaFlash('toast.message', 'The comment is now marked as the answer.');

    expect($thread->fresh()->accepted_reply_id)->toBe($reply->id);

    $this->actingAs($actor)
        ->get(route('forum.threads.show', $thread))
        ->assertInertia(fn (Assert $page) => $page->where('thread.accepted_reply_id', $reply->id)->where('can.acceptAnswer', true));

    $this->actingAs($actor)->patch(route('forum.threads.answer.update', $thread), ['reply_id' => null]);

    expect($thread->fresh()->accepted_reply_id)->toBeNull();
})->with(['the author', 'an admin']);

test('only a comment from the same thread can be its answer', function () {
    $author = User::factory()->create();
    $thread = ForumThread::factory()->for($author, 'author')->create();
    $elsewhere = ForumReply::factory()->create();

    $this->actingAs($author)
        ->patch(route('forum.threads.answer.update', $thread), ['reply_id' => $elsewhere->id])
        ->assertSessionHasErrors('reply_id');

    expect($thread->fresh()->accepted_reply_id)->toBeNull();
});

test('another member cannot mark the answer', function () {
    $thread = ForumThread::factory()->create();
    $reply = ForumReply::factory()->for($thread, 'thread')->create();

    $this->actingAs(User::factory()->create())
        ->patch(route('forum.threads.answer.update', $thread), ['reply_id' => $reply->id])
        ->assertForbidden();
});

test('the live changes of a thread carry its accepted answer', function () {
    $thread = ForumThread::factory()->create();
    $reply = ForumReply::factory()->for($thread, 'thread')->create();
    $thread->forceFill(['accepted_reply_id' => $reply->id])->save();

    $this->actingAs(User::factory()->create())
        ->getJson(route('forum.threads.changes.index', $thread))
        ->assertJsonPath('thread.accepted_reply_id', $reply->id);
});

test('deleting the accepted comment clears the mark', function () {
    $thread = ForumThread::factory()->create();
    $reply = ForumReply::factory()->for($thread, 'thread')->create();
    $thread->forceFill(['accepted_reply_id' => $reply->id])->save();

    $reply->delete();

    expect($thread->fresh()->accepted_reply_id)->toBeNull();
});
