<?php

use App\Enums\TicketStatus;
use App\Enums\WaitingOn;
use App\Models\Ticket;
use App\Models\User;
use App\Notifications\TicketStatusChanged;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schedule;

uses(RefreshDatabase::class);

test('tickets left resolved long enough are closed and their requesters told', function () {
    Notification::fake();
    config()->set('helpdesk.auto_close_days', 7);
    $stale = Ticket::factory()->resolved()->create(['resolved_at' => now()->subDays(8)]);
    $shipped = Ticket::factory()->featureRequest()->create(['status' => TicketStatus::Shipped, 'resolved_at' => now()->subDays(10)]);
    $recent = Ticket::factory()->resolved()->create(['resolved_at' => now()->subDays(3)]);
    $open = Ticket::factory()->create();

    $this->artisan('tickets:close-resolved')
        ->expectsOutputToContain('Closed 2 tickets resolved 7 or more days ago.')
        ->assertSuccessful();

    expect($stale->fresh()->status)->toBe(TicketStatus::Closed)
        ->and($stale->fresh()->waiting_on)->toBeNull()
        ->and($stale->events()->sole()->user_id)->toBeNull()
        ->and($shipped->fresh()->status)->toBe(TicketStatus::Closed)
        ->and($recent->fresh()->status)->toBe(TicketStatus::Resolved)
        ->and($recent->fresh()->waiting_on)->toBe(WaitingOn::Requester)
        ->and($open->fresh()->status)->toBe(TicketStatus::Open);

    Notification::assertSentTo($stale->requester, TicketStatusChanged::class, fn (TicketStatusChanged $notification): bool => $notification->actor === null && $notification->status === TicketStatus::Closed);
    Notification::assertNotSentTo($recent->requester, TicketStatusChanged::class);
});

test('the number of days can be given on the command line', function () {
    $ticket = Ticket::factory()->resolved()->create(['resolved_at' => now()->subDays(2)]);

    $this->artisan('tickets:close-resolved', ['--days' => 1])->assertSuccessful();

    expect($ticket->fresh()->status)->toBe(TicketStatus::Closed);
});

test('a notification closed by the helpdesk itself reads as such', function () {
    $requester = User::factory()->create();
    $ticket = Ticket::factory()->for($requester, 'requester')->resolved()->create(['resolved_at' => now()->subDays(30)]);

    $this->artisan('tickets:close-resolved');

    $this->actingAs($requester)
        ->getJson(route('notifications.index'))
        ->assertJsonPath('data.0.message', "The helpdesk marked your bug report {$ticket->key} as Closed");
});

test('read notifications older than the limit are pruned', function () {
    config()->set('helpdesk.prune_notifications_after_days', 90);
    $user = User::factory()->create();
    $data = ['type' => 'App\Notifications\TicketCommented', 'notifiable_type' => User::class, 'notifiable_id' => $user->id, 'data' => []];
    $oldRead = DatabaseNotification::query()->forceCreate([...$data, 'id' => fake()->uuid(), 'read_at' => now()->subDays(91)]);
    $recentRead = DatabaseNotification::query()->forceCreate([...$data, 'id' => fake()->uuid(), 'read_at' => now()->subDays(10)]);
    $oldUnread = DatabaseNotification::query()->forceCreate([...$data, 'id' => fake()->uuid(), 'created_at' => now()->subYear()]);

    $this->artisan('notifications:prune')
        ->expectsOutputToContain('Removed 1 notification read 90 or more days ago.')
        ->assertSuccessful();

    expect(DatabaseNotification::query()->pluck('id')->all())->toEqualCanonicalizing([$recentRead->id, $oldUnread->id]);
});

test('the housekeeping runs every day', function () {
    $commands = collect(Schedule::events())->map(fn ($event): string => $event->command)->implode(' ');

    expect($commands)->toContain('tickets:close-resolved')
        ->toContain('notifications:prune')
        ->toContain('model:prune');
});
