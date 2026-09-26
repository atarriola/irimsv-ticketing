<?php

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Enums\TicketType;
use App\Models\Category;
use App\Models\Ticket;
use App\Models\TicketAttachment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/**
 * Build a valid ticket payload, optionally overriding fields.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function ticketPayload(array $overrides = []): array
{
    return [
        'type' => 'bug_report',
        'priority' => 'high',
        'subject' => 'Export button does nothing',
        'description' => 'Clicking export on the reports page has no effect.',
        ...$overrides,
    ];
}

test('a guest cannot open the ticket form or raise a ticket', function (string $method, string $routeName) {
    $this->{$method}(route($routeName), ticketPayload())->assertRedirect(route('login'));

    expect(Ticket::count())->toBe(0);
})->with([
    'form' => ['get', 'tickets.create'],
    'submission' => ['post', 'tickets.store'],
]);

test('the ticket form offers the types, priorities and categories', function () {
    $category = Category::factory()->create(['name' => 'Billing']);

    $this->actingAs(User::factory()->create())
        ->get(route('tickets.create', ['type' => 'feature_request']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Tickets/Create')
            ->has('types', 3)
            ->has('priorities', 4)
            ->where('categories.0.id', $category->id)
            ->where('categories.0.name', 'Billing')
            ->where('defaultType', 'feature_request'));
});

test('a user can raise a ticket and lands on its page', function () {
    $user = User::factory()->create();
    $category = Category::factory()->create();

    $response = $this->actingAs($user)->post(route('tickets.store'), ticketPayload(['category_id' => $category->id]));

    $ticket = Ticket::sole();

    $response
        ->assertRedirect(route('tickets.show', $ticket))
        ->assertInertiaFlash('toast.type', 'success');

    expect($ticket->user_id)->toBe($user->id);
    expect($ticket->category_id)->toBe($category->id);
    expect($ticket->type)->toBe(TicketType::BugReport);
    expect($ticket->priority)->toBe(TicketPriority::High);
    expect($ticket->status)->toBe(TicketStatus::Open);
    expect($ticket->subject)->toBe('Export button does nothing');
});

test('a user can raise a feature request', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('tickets.store'), ticketPayload(['type' => 'feature_request']));

    expect(Ticket::sole()->type)->toBe(TicketType::FeatureRequest);
});

test('a user cannot set the status or owner when raising a ticket', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    $this->actingAs($user)->post(route('tickets.store'), ticketPayload([
        'status' => 'closed',
        'user_id' => $otherUser->id,
    ]));

    $ticket = Ticket::sole();

    expect($ticket->status)->toBe(TicketStatus::Open);
    expect($ticket->user_id)->toBe($user->id);
});

test('raising a ticket requires a type, priority, subject and description', function () {
    $response = $this->actingAs(User::factory()->create())->post(route('tickets.store'), []);

    $response->assertSessionHasErrors([
        'type' => 'The type field is required.',
        'priority' => 'The priority field is required.',
        'subject' => 'The subject field is required.',
        'description' => 'The description field is required.',
    ]);
    expect(Ticket::count())->toBe(0);
});

test('raising a ticket rejects :field with an invalid value', function (string $field, mixed $value, string $message) {
    $response = $this->actingAs(User::factory()->create())
        ->post(route('tickets.store'), ticketPayload([$field => $value]));

    $response->assertSessionHasErrors([$field => $message]);
    expect(Ticket::count())->toBe(0);
})->with([
    'an unknown type' => ['type', 'complaint', 'The selected type is invalid.'],
    'an unknown priority' => ['priority', 'urgent', 'The selected priority is invalid.'],
    'a missing category' => ['category_id', 999, 'The selected category is invalid.'],
    'an overlong subject' => ['subject', str_repeat('a', 256), 'The subject field must not be greater than 255 characters.'],
]);

test('a user can attach screenshots when raising a ticket', function () {
    Storage::fake(TicketAttachment::DISK);
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('tickets.store'), ticketPayload([
            'attachments' => [UploadedFile::fake()->image('error.png', 800, 600), UploadedFile::fake()->image('console.jpg')],
        ]))
        ->assertSessionDoesntHaveErrors();

    $attachments = Ticket::sole()->attachments;

    expect($attachments->pluck('name')->all())->toBe(['error.png', 'console.jpg']);
    expect($attachments->first()->user_id)->toBe($user->id);
    expect($attachments->first()->mime_type)->toBe('image/png');
    expect($attachments->first()->size)->toBeGreaterThan(0);
    Storage::disk(TicketAttachment::DISK)->assertExists($attachments->pluck('path')->all());
});

test('raising a ticket rejects :description', function (Closure $attachments, string $field, string $message) {
    Storage::fake(TicketAttachment::DISK);

    $this->actingAs(User::factory()->create())
        ->post(route('tickets.store'), ticketPayload(['attachments' => $attachments()]))
        ->assertSessionHasErrors([$field => $message]);

    expect(Ticket::count())->toBe(0);
})->with([
    'a file that is not an image' => [
        fn (): array => [UploadedFile::fake()->create('notes.pdf', 100, 'application/pdf')],
        'attachments.0',
        'Each attachment must be a JPG, PNG, GIF or WebP image.',
    ],
    'an image over 5 MB' => [
        fn (): array => [UploadedFile::fake()->image('huge.png')->size(TicketAttachment::MAX_KILOBYTES + 1)],
        'attachments.0',
        'Each image must be 5 MB or smaller.',
    ],
    'more than five images' => [
        fn (): array => array_map(fn (int $number) => UploadedFile::fake()->image("shot-{$number}.png"), range(1, 6)),
        'attachments',
        'You can attach up to 5 images.',
    ],
]);
