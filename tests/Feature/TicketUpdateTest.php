<?php

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Enums\TicketType;
use App\Models\Ticket;
use App\Models\TicketAttachment;
use App\Models\TicketComment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('the edit form lists the screenshots already on the ticket', function () {
    $requester = User::factory()->create();
    $ticket = Ticket::factory()->for($requester, 'requester')->create();
    $attachment = TicketAttachment::factory()->for($ticket)->create(['name' => 'before.png']);

    $this->actingAs($requester)
        ->get(route('tickets.edit', $ticket))
        ->assertInertia(fn (Assert $page) => $page
            ->has('ticket.attachments', 1)
            ->where('ticket.attachments.0.id', $attachment->id)
            ->where('ticket.attachments.0.name', 'before.png'));
});

test('a requester can add screenshots while editing their ticket', function () {
    Storage::fake(TicketAttachment::DISK);
    $requester = User::factory()->create();
    $ticket = Ticket::factory()->for($requester, 'requester')->create();
    TicketAttachment::factory()->for($ticket)->create(['name' => 'before.png']);

    $this->actingAs($requester)
        ->put(route('tickets.update', $ticket), [
            'type' => 'bug_report',
            'priority' => 'low',
            'subject' => 'Still broken',
            'description' => 'Now with a screenshot.',
            'attachments' => [UploadedFile::fake()->image('after.png')],
        ])
        ->assertRedirect(route('tickets.show', $ticket))
        ->assertSessionDoesntHaveErrors();

    $attachments = $ticket->attachments()->get();

    expect($attachments->pluck('name')->all())->toBe(['before.png', 'after.png']);
    expect($attachments->last()->user_id)->toBe($requester->id);
    Storage::disk(TicketAttachment::DISK)->assertExists($attachments->last()->path);
});

test('editing cannot push a ticket past five images', function () {
    Storage::fake(TicketAttachment::DISK);
    $requester = User::factory()->create();
    $ticket = Ticket::factory()->for($requester, 'requester')->create();
    TicketAttachment::factory(4)->for($ticket)->create();

    $this->actingAs($requester)
        ->put(route('tickets.update', $ticket), [
            'type' => 'bug_report',
            'priority' => 'low',
            'subject' => 'Still broken',
            'description' => 'Two more screenshots.',
            'attachments' => [UploadedFile::fake()->image('five.png'), UploadedFile::fake()->image('six.png')],
        ])
        ->assertSessionHasErrors(['attachments' => 'A ticket can hold up to 5 images, and this one already has 4.']);

    expect($ticket->attachments()->count())->toBe(4);
});

test('a requester can open the edit form for their open ticket', function () {
    $requester = User::factory()->create();
    $ticket = Ticket::factory()->for($requester, 'requester')->create(['subject' => 'Old subject']);

    $this->actingAs($requester)
        ->get(route('tickets.edit', $ticket))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Tickets/Edit')
            ->where('ticket.id', $ticket->id)
            ->where('ticket.subject', 'Old subject')
            ->has('types', 3)
            ->has('priorities', 4));
});

test('a requester can update the details of their open ticket', function () {
    $requester = User::factory()->create();
    $ticket = Ticket::factory()->for($requester, 'requester')->create(['type' => TicketType::BugReport]);

    $response = $this->actingAs($requester)->put(route('tickets.update', $ticket), [
        'type' => 'feature_request',
        'priority' => 'critical',
        'subject' => 'New subject',
        'description' => 'New description',
        'status' => 'closed',
    ]);

    $response->assertRedirect(route('tickets.show', $ticket));

    $ticket->refresh();

    expect($ticket->type)->toBe(TicketType::FeatureRequest);
    expect($ticket->priority)->toBe(TicketPriority::Critical);
    expect($ticket->subject)->toBe('New subject');
    expect($ticket->status)->toBe(TicketStatus::Open);
});

test('updating a ticket validates its details', function () {
    $requester = User::factory()->create();
    $ticket = Ticket::factory()->for($requester, 'requester')->create(['subject' => 'Original']);

    $this->actingAs($requester)
        ->put(route('tickets.update', $ticket), ['type' => 'bug_report', 'priority' => 'low', 'subject' => '', 'description' => 'Still here'])
        ->assertSessionHasErrors(['subject' => 'The subject field is required.']);

    expect($ticket->fresh()->subject)->toBe('Original');
});

test('a requester cannot edit their ticket once it is resolved', function (string $method, string $routeName) {
    $requester = User::factory()->create();
    $ticket = Ticket::factory()->for($requester, 'requester')->resolved()->create(['subject' => 'Original']);

    $this->actingAs($requester)
        ->{$method}(route($routeName, $ticket), ['type' => 'bug_report', 'priority' => 'low', 'subject' => 'Changed', 'description' => 'Changed'])
        ->assertForbidden();

    expect($ticket->fresh()->subject)->toBe('Original');
})->with([
    'form' => ['get', 'tickets.edit'],
    'submission' => ['put', 'tickets.update'],
]);

test('a user cannot update a ticket raised by someone else', function () {
    $ticket = Ticket::factory()->create(['subject' => 'Original']);

    $this->actingAs(User::factory()->create())
        ->put(route('tickets.update', $ticket), ['type' => 'bug_report', 'priority' => 'low', 'subject' => 'Hijacked', 'description' => 'Hijacked'])
        ->assertForbidden();

    expect($ticket->fresh()->subject)->toBe('Original');
});

test('an admin can delete a ticket and its comments go with it', function () {
    $ticket = Ticket::factory()->featureRequest()->create();
    TicketComment::factory(2)->for($ticket)->create();

    $response = $this->actingAs(User::factory()->admin()->create())->delete(route('tickets.destroy', $ticket));

    $response->assertRedirect(route('tickets.index', ['group' => 'feature_requests']));
    expect(Ticket::count())->toBe(0);
    expect(TicketComment::count())->toBe(0);
});

test('a requester cannot delete their own ticket', function () {
    $requester = User::factory()->create();
    $ticket = Ticket::factory()->for($requester, 'requester')->create();

    $this->actingAs($requester)->delete(route('tickets.destroy', $ticket))->assertForbidden();

    expect(Ticket::count())->toBe(1);
});
