<?php

use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\TicketWatcher;
use App\Models\User;
use App\Notifications\TicketCommented;
use App\Notifications\TicketStatusChanged;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('a member can follow a shared ticket and say it affects them too', function () {
    $ticket = Ticket::factory()->shared()->create();
    $member = User::factory()->create();

    $this->actingAs($member)
        ->from(route('tickets.show', $ticket))
        ->put(route('tickets.watch.update', $ticket), ['is_affected' => true])
        ->assertRedirect(route('tickets.show', $ticket))
        ->assertInertiaFlash('toast.type', 'success');

    expect($ticket->watchers()->whereKey($member->id)->first()->pivot->is_affected)->toBeTrue();

    $this->actingAs($member)
        ->get(route('tickets.show', $ticket))
        ->assertInertia(fn (Assert $page) => $page
            ->where('ticket.is_watching', true)
            ->where('ticket.is_affected', true)
            ->where('ticket.supporters_count', 1));
});

test('following without being affected does not count as support', function () {
    $ticket = Ticket::factory()->shared()->create();
    $member = User::factory()->create();

    $this->actingAs($member)->put(route('tickets.watch.update', $ticket), ['is_affected' => false]);

    $this->actingAs($member)
        ->get(route('tickets.show', $ticket))
        ->assertInertia(fn (Assert $page) => $page
            ->where('ticket.is_watching', true)
            ->where('ticket.is_affected', false)
            ->where('ticket.supporters_count', 0));
});

test('a member can stop following a ticket', function () {
    $ticket = Ticket::factory()->shared()->create();
    $watcher = TicketWatcher::factory()->for($ticket)->affected()->create();

    $this->actingAs($watcher->user)
        ->delete(route('tickets.watch.destroy', $ticket))
        ->assertRedirect();

    expect($ticket->watchers()->count())->toBe(0);
});

test(':who cannot follow the ticket', function (string $who) {
    $requester = User::factory()->create();
    $ticket = Ticket::factory()->for($requester, 'requester')->create(['is_shared' => $who !== 'a member, when it is private']);
    $actor = $who === 'its requester' ? $requester : User::factory()->create();

    $this->actingAs($actor)
        ->put(route('tickets.watch.update', $ticket), ['is_affected' => true])
        ->assertForbidden();

    expect($ticket->watchers()->count())->toBe(0);
})->with(['its requester', 'a member, when it is private']);

test('followers are told about messages and status changes, but not about their own', function () {
    Notification::fake();
    $ticket = Ticket::factory()->shared()->create();
    $watcher = TicketWatcher::factory()->for($ticket)->create()->user;
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->postJson(route('tickets.comments.store', $ticket), ['body' => 'Looking into it.'])->assertCreated();
    $this->actingAs($admin)->patch(route('tickets.status.update', $ticket), ['status' => 'in_progress']);
    $this->actingAs($watcher)->postJson(route('tickets.comments.store', $ticket), ['body' => 'Same here.'])->assertCreated();

    Notification::assertSentTo($watcher, TicketCommented::class, fn (TicketCommented $notification): bool => $notification->comment->body === 'Looking into it.');
    Notification::assertSentTo($watcher, TicketStatusChanged::class, fn (TicketStatusChanged $notification): bool => $notification->status === TicketStatus::InProgress);
    Notification::assertNotSentTo($watcher, TicketCommented::class, fn (TicketCommented $notification): bool => $notification->comment->body === 'Same here.');
    Notification::assertSentTo($ticket->requester, TicketCommented::class, fn (TicketCommented $notification): bool => $notification->comment->body === 'Same here.');
});

test('the list of feature requests can be sorted by support', function () {
    $admin = User::factory()->admin()->create();
    $quiet = Ticket::factory()->featureRequest()->create();
    $popular = Ticket::factory()->featureRequest()->create();
    TicketWatcher::factory(2)->for($popular)->affected()->create();
    TicketWatcher::factory()->for($popular)->create();

    $this->actingAs($admin)
        ->get(route('tickets.index', ['view' => 'list', 'group' => 'feature_requests', 'sort' => 'votes']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.sort', 'votes')
            ->where('tickets.data.0.id', $popular->id)
            ->where('tickets.data.0.supporters_count', 2)
            ->where('tickets.data.1.id', $quiet->id));
});

test('the support count is shown on the board', function () {
    $ticket = Ticket::factory()->shared()->create(['type' => 'problem']);
    TicketWatcher::factory()->for($ticket)->affected()->create();

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('tickets.index'))
        ->assertInertia(fn (Assert $page) => $page->where('columns.0.tickets.0.supporters_count', 1));
});
