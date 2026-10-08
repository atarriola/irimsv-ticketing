<?php

use App\Enums\TicketType;
use App\Models\Ticket;
use App\Models\TicketAttachment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('a member cannot open, read or download from a private ticket raised by someone else', function () {
    $ticket = Ticket::factory()->create();
    $attachment = TicketAttachment::factory()->for($ticket)->create();
    $member = User::factory()->create();

    $this->actingAs($member)->get(route('tickets.show', $ticket))->assertForbidden();
    $this->actingAs($member)->getJson(route('tickets.comments.index', $ticket))->assertForbidden();
    $this->actingAs($member)->postJson(route('tickets.comments.store', $ticket), ['body' => 'Me too'])->assertForbidden();
    $this->actingAs($member)->get(route('tickets.attachments.show', [$ticket, $attachment]))->assertForbidden();
});

test('a member can open a shared ticket raised by someone else and join its conversation', function () {
    $ticket = Ticket::factory()->shared()->create();

    $this->actingAs(User::factory()->create())
        ->get(route('tickets.show', $ticket))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Tickets/Show')
            ->where('ticket.is_shared', true)
            ->where('can.comment', true)
            ->where('can.watch', true)
            ->where('can.update', false)
            ->where('can.changeStatus', false));
});

test('the requester and the helpdesk can open a private ticket', function () {
    $requester = User::factory()->create();
    $ticket = Ticket::factory()->for($requester, 'requester')->create();

    $this->actingAs($requester)->get(route('tickets.show', $ticket))->assertOk();
    $this->actingAs(User::factory()->admin()->create())->get(route('tickets.show', $ticket))->assertOk();
});

test('a member lists their own tickets and the shared ones, and counts only those', function () {
    $member = User::factory()->create();
    $own = Ticket::factory()->for($member, 'requester')->create(['type' => TicketType::BugReport]);
    $shared = Ticket::factory()->shared()->create(['type' => TicketType::Problem]);
    Ticket::factory()->create(['type' => TicketType::BugReport]);
    Ticket::factory()->featureRequest()->create();

    $this->actingAs($member)
        ->get(route('tickets.index', ['view' => 'list']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('tickets.data', 2)
            ->where('tickets.data.0.id', $shared->id)
            ->where('tickets.data.1.id', $own->id)
            ->where('groups.0.key', 'all')
            ->where('groups.0.count', 2)
            ->where('groups.1.count', 2)
            ->where('groups.2.count', 0));
});

test('the group counts only cover active tickets', function () {
    $member = User::factory()->create();
    Ticket::factory()->for($member, 'requester')->create(['type' => TicketType::BugReport]);
    Ticket::factory()->for($member, 'requester')->closed()->create(['type' => TicketType::BugReport]);

    $this->actingAs($member)
        ->get(route('tickets.index'))
        ->assertInertia(fn (Assert $page) => $page->where('groups.1.count', 1));
});

test('the board of a member only holds the tickets they may read', function () {
    $member = User::factory()->create();
    Ticket::factory()->for($member, 'requester')->create(['type' => TicketType::BugReport]);
    Ticket::factory()->shared()->create(['type' => TicketType::BugReport]);
    Ticket::factory(3)->create(['type' => TicketType::BugReport]);

    $this->actingAs($member)
        ->get(route('tickets.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('columns.0.total', 2)
            ->has('columns.0.tickets', 2));
});

test('searching only finds tickets the member may read', function () {
    $member = User::factory()->create();
    $visible = Ticket::factory()->shared()->create(['type' => TicketType::BugReport, 'subject' => 'Payroll page is slow']);
    Ticket::factory()->create(['type' => TicketType::BugReport, 'subject' => 'Payroll export fails']);

    $this->actingAs($member)
        ->get(route('tickets.index', ['view' => 'list', 'q' => 'payroll']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('tickets.data', 1)
            ->where('tickets.data.0.id', $visible->id));
});

test('tickets can be searched by the name of the person who raised them', function () {
    $requester = User::factory()->create(['firstname' => 'Maricel', 'lastname' => 'Dizon']);
    $match = Ticket::factory()->for($requester, 'requester')->create(['type' => TicketType::BugReport]);
    Ticket::factory()->create(['type' => TicketType::BugReport]);

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('tickets.index', ['view' => 'list', 'q' => 'maricel']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('tickets.data', 1)
            ->where('tickets.data.0.id', $match->id));
});

test('an admin sees every ticket, private or shared', function () {
    Ticket::factory(2)->create(['type' => TicketType::BugReport]);
    Ticket::factory()->shared()->create(['type' => TicketType::Problem]);

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('tickets.index', ['view' => 'list']))
        ->assertInertia(fn (Assert $page) => $page->has('tickets.data', 3)->where('groups.1.count', 3));
});

test('similar tickets are suggested from what the member may read', function () {
    $member = User::factory()->create();
    $own = Ticket::factory()->for($member, 'requester')->create(['subject' => 'Cannot print the report card']);
    $shared = Ticket::factory()->shared()->resolved()->create(['subject' => 'Report card printing blank']);
    Ticket::factory()->create(['subject' => 'Report card is wrong']);

    $this->actingAs($member)
        ->getJson(route('tickets.similar', ['q' => 'report card']))
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.*.id', fn (array $ids) => collect($ids)->sort()->values()->all() === collect([$own->id, $shared->id])->sort()->values()->all())
        ->assertJsonPath('data.0.url', fn (string $url) => str_contains($url, '/tickets/'));
});

test('a short term suggests nothing', function () {
    Ticket::factory()->shared()->create(['subject' => 'Report card is wrong']);

    $this->actingAs(User::factory()->create())
        ->getJson(route('tickets.similar', ['q' => 're']))
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

test('a requester chooses whether to share their ticket when raising and editing it', function () {
    $requester = User::factory()->create();

    $this->actingAs($requester)->post(route('tickets.store'), [
        'type' => 'bug_report',
        'priority' => 'low',
        'subject' => 'Shared problem',
        'description' => 'Everyone has this.',
        'is_shared' => '1',
    ]);

    $ticket = Ticket::sole();
    expect($ticket->is_shared)->toBeTrue();

    $this->actingAs($requester)->put(route('tickets.update', $ticket), [
        'type' => 'bug_report',
        'subject' => 'Shared problem',
        'description' => 'On second thought, keep it private.',
        'is_shared' => '0',
    ]);

    expect($ticket->fresh()->is_shared)->toBeFalse();
});

test('a ticket is private unless it is shared on purpose', function () {
    $this->actingAs(User::factory()->create())->post(route('tickets.store'), [
        'type' => 'bug_report',
        'priority' => 'low',
        'subject' => 'Private problem',
        'description' => 'Only for the helpdesk.',
    ]);

    expect(Ticket::sole()->is_shared)->toBeFalse();
});
