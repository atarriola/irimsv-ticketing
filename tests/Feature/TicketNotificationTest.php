<?php

use App\Models\Ticket;
use App\Models\User;
use App\Notifications\TicketCommented;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

test('a message from :role notifies the ticket requester but not the person who wrote it', function (string $role) {
    Notification::fake();
    $requester = User::factory()->create();
    $ticket = Ticket::factory()->for($requester, 'requester')->create();
    $writer = $role === 'an admin' ? User::factory()->admin()->create() : User::factory()->create();

    $this->actingAs($writer)
        ->postJson(route('tickets.comments.store', $ticket), ['body' => 'We are looking into it.'])
        ->assertCreated();

    Notification::assertSentTo($requester, TicketCommented::class, fn (TicketCommented $notification): bool => $notification->ticket->is($ticket) && $notification->comment->body === 'We are looking into it.');
    Notification::assertNotSentTo($writer, TicketCommented::class);
    Notification::assertCount(1);
})->with(['an admin', 'another member']);

test('a message from the requester on their own ticket notifies nobody', function () {
    Notification::fake();
    $requester = User::factory()->create();
    $ticket = Ticket::factory()->for($requester, 'requester')->create();

    $this->actingAs($requester)
        ->postJson(route('tickets.comments.store', $ticket), ['body' => 'Any update on this?'])
        ->assertCreated();

    Notification::assertNothingSent();
});

test('a stored ticket notification carries what the bell shows and opens the ticket', function () {
    $requester = User::factory()->create();
    $ticket = Ticket::factory()->for($requester, 'requester')->featureRequest()->create();
    $admin = User::factory()->admin()->create(['firstname' => 'Maria', 'lastname' => 'Santos']);
    $this->actingAs($admin)->postJson(route('tickets.comments.store', $ticket), ['body' => 'Scheduled for the next release.'])->assertCreated();

    $this->actingAs($requester)
        ->getJson(route('notifications.index'))
        ->assertOk()
        ->assertJsonPath('unread_count', 1)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.kind', 'ticket')
        ->assertJsonPath('data.0.message', "Maria Santos commented on your feature request {$ticket->key}")
        ->assertJsonPath('data.0.excerpt', 'Scheduled for the next release.')
        ->assertJsonPath('data.0.url', route('tickets.show', $ticket))
        ->assertJsonPath('data.0.is_read', false);
});

test('the notification names the kind of ticket that was commented on', function (string $type, string $label) {
    $requester = User::factory()->create();
    $ticket = Ticket::factory()->for($requester, 'requester')->create(['type' => $type]);
    $admin = User::factory()->admin()->create(['firstname' => 'Jose', 'lastname' => 'Cruz']);
    $this->actingAs($admin)->postJson(route('tickets.comments.store', $ticket), ['body' => 'Noted.'])->assertCreated();

    $this->actingAs($requester)
        ->getJson(route('notifications.index'))
        ->assertJsonPath('data.0.message', "Jose Cruz commented on your {$label} {$ticket->key}");
})->with([
    'bug report' => ['bug_report', 'bug report'],
    'problem' => ['problem', 'problem'],
    'feature request' => ['feature_request', 'feature request'],
]);
