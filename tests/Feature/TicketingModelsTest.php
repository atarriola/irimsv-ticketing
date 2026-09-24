<?php

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Enums\TicketType;
use App\Enums\UserRole;
use App\Models\ForumReply;
use App\Models\ForumThread;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('a user raises a ticket that starts open with medium priority', function () {
    $user = User::factory()->create();

    $ticket = $user->tickets()->create([
        'type' => TicketType::FeatureRequest,
        'subject' => 'Add dark mode',
        'description' => 'A dark theme would help at night.',
    ]);

    expect($ticket->status)->toBe(TicketStatus::Open)
        ->and($ticket->priority)->toBe(TicketPriority::Medium)
        ->and($ticket->fresh()->type)->toBe(TicketType::FeatureRequest)
        ->and($ticket->requester->is($user))->toBeTrue();
});

test('status cannot be mass assigned when raising a ticket', function () {
    $user = User::factory()->create();

    $ticket = $user->tickets()->create([
        'type' => TicketType::Problem,
        'subject' => 'Printer offline',
        'description' => 'The printer is unreachable.',
        'status' => TicketStatus::Closed,
    ]);

    expect($ticket->status)->toBe(TicketStatus::Open);
});

test('marking a ticket keeps the resolution timestamps in sync', function () {
    $ticket = Ticket::factory()->create();

    $ticket->markAs(TicketStatus::Resolved);
    expect($ticket->fresh()->resolved_at)->not->toBeNull()
        ->and($ticket->fresh()->closed_at)->toBeNull();

    $ticket->markAs(TicketStatus::Closed);
    expect($ticket->fresh()->closed_at)->not->toBeNull();

    $ticket->markAs(TicketStatus::Open);
    expect($ticket->fresh()->resolved_at)->toBeNull()
        ->and($ticket->fresh()->closed_at)->toBeNull();
});

test('the active scope excludes resolved and closed tickets', function () {
    $open = Ticket::factory()->create();
    $inProgress = Ticket::factory()->inProgress()->create();
    Ticket::factory()->resolved()->create();
    Ticket::factory()->closed()->create();

    expect(Ticket::active()->pluck('id')->all())
        ->toEqualCanonicalizing([$open->id, $inProgress->id]);
});

test('tickets can be ordered from most to least urgent', function () {
    foreach ([TicketPriority::Medium, TicketPriority::Critical, TicketPriority::Low, TicketPriority::High] as $priority) {
        Ticket::factory()->create(['priority' => $priority]);
    }

    expect(Ticket::mostUrgentFirst()->get()->map(fn (Ticket $ticket) => $ticket->priority)->all())
        ->toBe([TicketPriority::Critical, TicketPriority::High, TicketPriority::Medium, TicketPriority::Low]);
});

test('an admin can comment on a ticket and new users are regular users', function () {
    $admin = User::factory()->admin()->create();
    $ticket = Ticket::factory()->inProgress()->create();

    TicketComment::factory()->for($ticket)->for($admin, 'author')->create();

    expect($admin->isAdmin())->toBeTrue()
        ->and(User::factory()->create()->role)->toBe(UserRole::User)
        ->and($ticket->comments()->first()->author->is($admin))->toBeTrue();
});

test('role cannot be mass assigned', function () {
    $user = User::create([
        'name' => 'Mallory',
        'email' => 'mallory@example.com',
        'password' => 'password',
        'role' => UserRole::Admin,
    ]);

    expect($user->fresh()->isAdmin())->toBeFalse();
});

test('a forum thread collects replies and is removed with them', function () {
    $thread = ForumThread::factory()->create();
    ForumReply::factory(3)->for($thread, 'thread')->create();

    expect($thread->is_locked)->toBeFalse()
        ->and($thread->replies)->toHaveCount(3)
        ->and($thread->author)->toBeInstanceOf(User::class);

    $thread->delete();

    expect(ForumReply::count())->toBe(0);
});
