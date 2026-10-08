<?php

use App\Models\Ticket;
use App\Models\TicketAttachment;
use App\Models\TicketComment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

test('an author can edit their own message and it is marked as edited', function () {
    $requester = User::factory()->create();
    $ticket = Ticket::factory()->for($requester, 'requester')->create();
    $comment = TicketComment::factory()->for($ticket)->for($requester, 'author')->create(['body' => 'Typo here', 'created_at' => now()->subMinute(), 'updated_at' => now()->subMinute()]);

    $this->actingAs($requester)
        ->putJson(route('ticket-comments.update', $comment), ['body' => 'Fixed here'])
        ->assertOk()
        ->assertJsonPath('comment.body', 'Fixed here')
        ->assertJsonPath('comment.is_edited', true);

    expect($comment->fresh()->body)->toBe('Fixed here');
});

test(':who cannot edit a message', function (string $who) {
    $requester = User::factory()->create();
    $ticket = Ticket::factory()->for($requester, 'requester')->shared()->create();
    $comment = TicketComment::factory()->for($ticket)->for($requester, 'author')->create(['body' => 'Original']);
    $actor = $who === 'an admin' ? User::factory()->admin()->create() : User::factory()->create();

    $this->actingAs($actor)
        ->putJson(route('ticket-comments.update', $comment), ['body' => 'Changed'])
        ->assertForbidden();

    expect($comment->fresh()->body)->toBe('Original');
})->with(['an admin', 'another member']);

test('a message cannot be edited to nothing', function () {
    $requester = User::factory()->create();
    $comment = TicketComment::factory()->for(Ticket::factory()->for($requester, 'requester'))->for($requester, 'author')->create();

    $this->actingAs($requester)
        ->putJson(route('ticket-comments.update', $comment), ['body' => ''])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['body' => 'The message field is required.']);
});

test('images can be sent with a message and are served with the ticket', function () {
    Storage::fake(TicketAttachment::DISK);
    $requester = User::factory()->create();
    $ticket = Ticket::factory()->for($requester, 'requester')->create();

    $response = $this->actingAs($requester)
        ->post(route('tickets.comments.store', $ticket), [
            'body' => 'Here is what I see.',
            'attachments' => [UploadedFile::fake()->image('screen.png'), UploadedFile::fake()->image('error.jpg')],
        ], ['Accept' => 'application/json'])
        ->assertCreated()
        ->assertJsonCount(2, 'comment.attachments')
        ->assertJsonPath('comment.attachments.0.name', 'screen.png');

    $attachment = TicketComment::sole()->attachments()->first();

    expect($attachment->ticket_id)->toBe($ticket->id);
    Storage::disk(TicketAttachment::DISK)->assertExists($attachment->path);

    $this->actingAs($requester)->get($response->json('comment.attachments.0.url'))->assertOk();
});

test('images sent with a message do not count as the ticket\'s own screenshots', function () {
    Storage::fake(TicketAttachment::DISK);
    $requester = User::factory()->create();
    $ticket = Ticket::factory()->for($requester, 'requester')->create();

    $this->actingAs($requester)->post(route('tickets.comments.store', $ticket), ['body' => 'See attached', 'attachments' => [UploadedFile::fake()->image('a.png')]], ['Accept' => 'application/json']);

    expect($ticket->attachments()->count())->toBe(1)
        ->and($ticket->screenshots()->count())->toBe(0);
});

test('a message takes at most three images', function () {
    Storage::fake(TicketAttachment::DISK);
    $requester = User::factory()->create();
    $ticket = Ticket::factory()->for($requester, 'requester')->create();

    $this->actingAs($requester)
        ->post(route('tickets.comments.store', $ticket), [
            'body' => 'Too many',
            'attachments' => array_map(fn (int $number) => UploadedFile::fake()->image("shot-{$number}.png"), range(1, 4)),
        ], ['Accept' => 'application/json'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['attachments' => 'You can attach up to 3 images to a message.']);
});

test('deleting a message removes its images', function () {
    Storage::fake(TicketAttachment::DISK);
    $requester = User::factory()->create();
    $ticket = Ticket::factory()->for($requester, 'requester')->create();
    $this->actingAs($requester)->post(route('tickets.comments.store', $ticket), ['body' => 'See attached', 'attachments' => [UploadedFile::fake()->image('a.png')]], ['Accept' => 'application/json']);
    $attachment = TicketAttachment::sole();

    $this->actingAs($requester)->deleteJson(route('ticket-comments.destroy', TicketComment::sole()))->assertOk();

    expect(TicketAttachment::count())->toBe(0);
    Storage::disk(TicketAttachment::DISK)->assertMissing($attachment->path);
});
