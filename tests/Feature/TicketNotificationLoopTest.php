<?php

use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\User;
use App\Models\UserPreference;
use App\Notifications\TicketCommented;
use App\Notifications\TicketRaised;
use App\Notifications\TicketStatusChanged;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

/**
 * A valid ticket payload.
 *
 * @return array<string, string>
 */
function raisedTicketPayload(): array
{
    return ['type' => 'problem', 'priority' => 'high', 'subject' => 'Cannot sign in', 'description' => 'The password page loops.'];
}

test('raising a ticket tells every helpdesk administrator except the one who raised it', function () {
    Notification::fake();
    $admins = User::factory(2)->admin()->create();
    $raisingAdmin = User::factory()->admin()->create();
    $member = User::factory()->create();

    $this->actingAs($raisingAdmin)->post(route('tickets.store'), raisedTicketPayload());

    $ticket = Ticket::sole();

    Notification::assertSentTo($admins, TicketRaised::class, fn (TicketRaised $notification): bool => $notification->ticket->is($ticket));
    Notification::assertNotSentTo($raisingAdmin, TicketRaised::class);
    Notification::assertNotSentTo($member, TicketRaised::class);
});

test('a stored raised notification names the requester and opens the ticket', function () {
    $admin = User::factory()->admin()->create();
    $requester = User::factory()->create(['firstname' => 'Ana', 'lastname' => 'Reyes']);

    $this->actingAs($requester)->post(route('tickets.store'), raisedTicketPayload());

    $ticket = Ticket::sole();

    $this->actingAs($admin)
        ->getJson(route('notifications.index'))
        ->assertJsonPath('unread_count', 1)
        ->assertJsonPath('data.0.kind', 'ticket')
        ->assertJsonPath('data.0.message', "Ana Reyes raised a problem {$ticket->key}")
        ->assertJsonPath('data.0.url', route('tickets.show', $ticket));
});

test('a reply from the requester tells the helpdesk administrators', function () {
    Notification::fake();
    $admins = User::factory(2)->admin()->create();
    $requester = User::factory()->create();
    $ticket = Ticket::factory()->for($requester, 'requester')->create();

    $this->actingAs($requester)->postJson(route('tickets.comments.store', $ticket), ['body' => 'Any update?'])->assertCreated();

    Notification::assertSentTo($admins, TicketCommented::class, fn (TicketCommented $notification): bool => $notification->comment->body === 'Any update?');
    Notification::assertNotSentTo($requester, TicketCommented::class);
});

test('a reply from an administrator tells the requester but not the other administrators', function () {
    Notification::fake();
    $admin = User::factory()->admin()->create();
    $otherAdmin = User::factory()->admin()->create();
    $requester = User::factory()->create();
    $ticket = Ticket::factory()->for($requester, 'requester')->create();

    $this->actingAs($admin)->postJson(route('tickets.comments.store', $ticket), ['body' => 'Fixed.'])->assertCreated();

    Notification::assertSentTo($requester, TicketCommented::class);
    Notification::assertNotSentTo([$admin, $otherAdmin], TicketCommented::class);
});

test('the administrators notification about a reply does not call the ticket theirs', function () {
    $admin = User::factory()->admin()->create();
    $requester = User::factory()->create(['firstname' => 'Ana', 'lastname' => 'Reyes']);
    $ticket = Ticket::factory()->for($requester, 'requester')->create(['type' => 'bug_report']);

    $this->actingAs($requester)->postJson(route('tickets.comments.store', $ticket), ['body' => 'Any update?'])->assertCreated();

    $this->actingAs($admin)
        ->getJson(route('notifications.index'))
        ->assertJsonPath('data.0.message', "Ana Reyes commented on bug report {$ticket->key}")
        ->assertJsonPath('data.0.excerpt', 'Any update?');
});

test('a status change tells the requester, naming the status, but not the administrator who made it', function () {
    Notification::fake();
    $admin = User::factory()->admin()->create();
    $requester = User::factory()->create();
    $ticket = Ticket::factory()->for($requester, 'requester')->create();

    $this->actingAs($admin)->patch(route('tickets.status.update', $ticket), ['status' => 'resolved']);

    Notification::assertSentTo($requester, TicketStatusChanged::class, fn (TicketStatusChanged $notification): bool => $notification->status === TicketStatus::Resolved && $notification->actor->is($admin));
    Notification::assertNotSentTo($admin, TicketStatusChanged::class);
});

test('a stored status notification reads naturally and opens the ticket', function () {
    $admin = User::factory()->admin()->create(['firstname' => 'Jose', 'lastname' => 'Cruz']);
    $requester = User::factory()->create();
    $ticket = Ticket::factory()->for($requester, 'requester')->featureRequest()->create();

    $this->actingAs($admin)->patch(route('tickets.status.update', $ticket), ['status' => 'planned']);

    $this->actingAs($requester)
        ->getJson(route('notifications.index'))
        ->assertJsonPath('data.0.kind', 'ticket')
        ->assertJsonPath('data.0.message', "Jose Cruz marked your feature request {$ticket->key} as Planned")
        ->assertJsonPath('data.0.url', route('tickets.show', $ticket));
});

test('a requester reopening their ticket tells the helpdesk', function () {
    Notification::fake();
    $admin = User::factory()->admin()->create();
    $requester = User::factory()->create();
    $ticket = Ticket::factory()->for($requester, 'requester')->resolved()->create();

    $this->actingAs($requester)->patch(route('tickets.status.update', $ticket), ['status' => 'open']);

    Notification::assertSentTo($admin, TicketStatusChanged::class, fn (TicketStatusChanged $notification): bool => $notification->status === TicketStatus::Open);
    Notification::assertNotSentTo($requester, TicketStatusChanged::class);
});

test('no email is queued while email notifications are off for the helpdesk', function () {
    Notification::fake();
    config()->set('helpdesk.email_notifications', false);
    $admin = User::factory()->admin()->create();
    $requester = User::factory()->create();

    $this->actingAs($admin)->postJson(route('tickets.comments.store', Ticket::factory()->for($requester, 'requester')->create()), ['body' => 'Hello'])->assertCreated();

    Notification::assertSentTo($requester, TicketCommented::class, fn (TicketCommented $notification, array $channels): bool => $channels === ['database', 'broadcast']);
});

test('ticket notifications go by email as well, once turned on, unless the person turned that off', function () {
    Notification::fake();
    config()->set('helpdesk.email_notifications', true);
    $admin = User::factory()->admin()->create();
    $wantsEmail = User::factory()->create();
    $noEmail = User::factory()->create();
    UserPreference::query()->create(['user_id' => $noEmail->id, 'email_notifications' => false]);
    $noAddress = User::factory()->create(['email' => '']);

    foreach ([$wantsEmail, $noEmail, $noAddress] as $requester) {
        $this->actingAs($admin)->postJson(route('tickets.comments.store', Ticket::factory()->for($requester, 'requester')->create()), ['body' => 'Hello'])->assertCreated();
    }

    Notification::assertSentTo($wantsEmail, TicketCommented::class, fn (TicketCommented $notification, array $channels): bool => $channels === ['database', 'broadcast', 'mail']);
    Notification::assertSentTo($noEmail, TicketCommented::class, fn (TicketCommented $notification, array $channels): bool => $channels === ['database', 'broadcast']);
    Notification::assertSentTo($noAddress, TicketCommented::class, fn (TicketCommented $notification, array $channels): bool => $channels === ['database', 'broadcast']);
});

test('the email about a reply links to the ticket', function () {
    $requester = User::factory()->create();
    $ticket = Ticket::factory()->for($requester, 'requester')->create(['subject' => 'Printer offline']);
    $admin = User::factory()->admin()->create(['firstname' => 'Jose', 'lastname' => 'Cruz']);

    $this->actingAs($admin)->postJson(route('tickets.comments.store', $ticket), ['body' => 'Try turning it off and on.'])->assertCreated();

    $mail = (new TicketCommented($ticket, $ticket->comments()->first()))->toMail($requester);

    expect($mail->subject)->toBe("[{$ticket->key}] Jose Cruz commented on Printer offline")
        ->and($mail->actionUrl)->toBe(route('tickets.show', $ticket))
        ->and($mail->introLines[0])->toContain("your bug report {$ticket->key}");
});
