<?php

use App\Enums\TicketEventKind;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('a deleted ticket disappears from lists but an admin can still open it', function () {
    $admin = User::factory()->admin()->create();
    $ticket = Ticket::factory()->create(['type' => 'bug_report']);
    $this->actingAs($admin)->delete(route('tickets.destroy', $ticket));

    $this->actingAs($admin)
        ->get(route('tickets.index', ['view' => 'list']))
        ->assertInertia(fn (Assert $page) => $page->has('tickets.data', 0));

    $this->actingAs($admin)
        ->get(route('tickets.show', $ticket))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('ticket.is_deleted', true)
            ->where('can.restore', true)
            ->where('can.update', false)
            ->where('can.comment', false)
            ->where('can.delete', false));
});

test('the requester cannot open their deleted ticket', function () {
    $requester = User::factory()->create();
    $ticket = Ticket::factory()->for($requester, 'requester')->create();
    $ticket->delete();

    $this->actingAs($requester)->get(route('tickets.show', $ticket))->assertForbidden();
    $this->actingAs($requester)->getJson(route('tickets.comments.index', $ticket))->assertNotFound();
});

test('an admin can list deleted tickets and restore one', function () {
    $admin = User::factory()->admin()->create();
    $ticket = Ticket::factory()->create(['type' => 'bug_report']);
    TicketComment::factory()->for($ticket)->create();
    $this->actingAs($admin)->delete(route('tickets.destroy', $ticket));

    $this->actingAs($admin)
        ->get(route('tickets.index', ['view' => 'list', 'trashed' => 1]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.trashed', true)
            ->has('tickets.data', 1)
            ->where('tickets.data.0.id', $ticket->id)
            ->where('tickets.data.0.is_deleted', true));

    $this->actingAs($admin)
        ->patch(route('tickets.restore', $ticket))
        ->assertRedirect(route('tickets.show', $ticket))
        ->assertInertiaFlash('toast.message', "{$ticket->key} has been restored.");

    expect($ticket->fresh()->trashed())->toBeFalse()
        ->and($ticket->comments()->count())->toBe(1)
        ->and($ticket->events()->pluck('kind')->all())->toBe([TicketEventKind::Deleted, TicketEventKind::Restored]);
});

test('a member never sees deleted tickets, even when asking for them', function () {
    $member = User::factory()->create();
    $ticket = Ticket::factory()->for($member, 'requester')->create(['type' => 'bug_report']);
    $ticket->delete();

    $this->actingAs($member)
        ->get(route('tickets.index', ['view' => 'list', 'trashed' => 1]))
        ->assertInertia(fn (Assert $page) => $page->where('filters.trashed', false)->has('tickets.data', 0));

    $this->actingAs($member)->patch(route('tickets.restore', $ticket))->assertForbidden();
});

test('tickets deleted long ago are removed for good when the models are pruned', function () {
    $old = Ticket::factory()->create();
    $old->delete();
    Ticket::withTrashed()->whereKey($old->id)->update(['deleted_at' => now()->subDays(31)]);
    $recent = Ticket::factory()->create();
    $recent->delete();

    $this->artisan('model:prune', ['--model' => [Ticket::class]])->assertSuccessful();

    expect(Ticket::withTrashed()->pluck('id')->all())->toBe([$recent->id]);
});
