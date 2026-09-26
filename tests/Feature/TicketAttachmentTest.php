<?php

use App\Models\Ticket;
use App\Models\TicketAttachment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(fn () => Storage::fake(TicketAttachment::DISK));

/**
 * Put a real PNG on the attachments disk and record it against the ticket.
 */
function attachImage(Ticket $ticket, string $name = 'error.png'): TicketAttachment
{
    $attachment = TicketAttachment::factory()->for($ticket)->create(['name' => $name, 'mime_type' => 'image/png']);

    // The fake image's temporary file only lives as long as the object does, so it is kept until it has been copied.
    $image = UploadedFile::fake()->image($name);
    Storage::disk(TicketAttachment::DISK)->put($attachment->path, file_get_contents($image->getPathname()));

    return $attachment;
}

test('a guest is sent to the login page when opening an attachment', function () {
    $attachment = attachImage(Ticket::factory()->create());

    $this->get(route('tickets.attachments.show', [$attachment->ticket_id, $attachment]))->assertRedirect(route('login'));
});

test('an image attached to a ticket can be opened by :viewer', function (string $viewer) {
    $requester = User::factory()->create();
    $ticket = Ticket::factory()->for($requester, 'requester')->create();
    $attachment = attachImage($ticket, 'error.png');

    $this->actingAs($viewer === 'an admin' ? User::factory()->admin()->create() : $requester)
        ->get(route('tickets.attachments.show', [$ticket, $attachment]))
        ->assertOk()
        ->assertHeader('Content-Type', 'image/png')
        ->assertHeader('Content-Disposition', 'inline; filename=error.png');
})->with(['the requester', 'an admin']);

test('a user cannot open an image attached to somebody else\'s ticket', function () {
    $attachment = attachImage(Ticket::factory()->create());

    $this->actingAs(User::factory()->create())
        ->get(route('tickets.attachments.show', [$attachment->ticket_id, $attachment]))
        ->assertForbidden();
});

test('an attachment is only found under its own ticket', function () {
    $requester = User::factory()->create();
    $attachment = attachImage(Ticket::factory()->for($requester, 'requester')->create());
    $otherTicket = Ticket::factory()->for($requester, 'requester')->create();

    $this->actingAs($requester)
        ->get(route('tickets.attachments.show', [$otherTicket, $attachment]))
        ->assertNotFound();
});

test('an attachment whose file is gone is not found', function () {
    $requester = User::factory()->create();
    $ticket = Ticket::factory()->for($requester, 'requester')->create();
    $attachment = TicketAttachment::factory()->for($ticket)->create();

    $this->actingAs($requester)
        ->get(route('tickets.attachments.show', [$ticket, $attachment]))
        ->assertNotFound();
});

test('a requester can remove an image from their open ticket, and its file goes with it', function () {
    $requester = User::factory()->create();
    $ticket = Ticket::factory()->for($requester, 'requester')->create();
    $attachment = attachImage($ticket);

    $this->actingAs($requester)
        ->from(route('tickets.edit', $ticket))
        ->delete(route('tickets.attachments.destroy', [$ticket, $attachment]))
        ->assertRedirect(route('tickets.edit', $ticket))
        ->assertInertiaFlash('toast.type', 'success');

    expect(TicketAttachment::count())->toBe(0);
    Storage::disk(TicketAttachment::DISK)->assertMissing($attachment->path);
});

test('an admin can remove an image from any ticket', function () {
    $attachment = attachImage(Ticket::factory()->create());

    $this->actingAs(User::factory()->admin()->create())
        ->delete(route('tickets.attachments.destroy', [$attachment->ticket_id, $attachment]))
        ->assertRedirect();

    expect(TicketAttachment::count())->toBe(0);
});

test('an image cannot be removed by :who', function (string $who) {
    $requester = User::factory()->create();
    $ticket = $who === 'the requester once the ticket is closed'
        ? Ticket::factory()->closed()->for($requester, 'requester')->create()
        : Ticket::factory()->for($requester, 'requester')->create();
    $attachment = attachImage($ticket);
    $actor = $who === 'someone who did not raise the ticket' ? User::factory()->create() : $requester;

    $this->actingAs($actor)
        ->delete(route('tickets.attachments.destroy', [$ticket, $attachment]))
        ->assertForbidden();

    expect(TicketAttachment::count())->toBe(1);
    Storage::disk(TicketAttachment::DISK)->assertExists($attachment->path);
})->with(['someone who did not raise the ticket', 'the requester once the ticket is closed']);

test('deleting a ticket removes its image files', function () {
    $ticket = Ticket::factory()->create();
    $first = attachImage($ticket, 'one.png');
    $second = attachImage($ticket, 'two.png');

    $this->actingAs(User::factory()->admin()->create())->delete(route('tickets.destroy', $ticket));

    expect(TicketAttachment::count())->toBe(0);
    Storage::disk(TicketAttachment::DISK)->assertMissing([$first->path, $second->path]);
});
