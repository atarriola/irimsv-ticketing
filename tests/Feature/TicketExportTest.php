<?php

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('an admin can download the tickets a list shows as a CSV file', function () {
    $admin = User::factory()->admin()->create();
    $requester = User::factory()->create(['firstname' => 'Ana', 'lastname' => 'Reyes']);
    $ticket = Ticket::factory()->for($requester, 'requester')->create(['type' => 'bug_report', 'subject' => 'Printer offline', 'priority' => 'high']);
    Ticket::factory()->featureRequest()->create(['subject' => 'Dark mode']);

    $response = $this->actingAs($admin)->get(route('tickets.export', ['group' => 'issues']));

    $response->assertOk()
        ->assertHeader('Content-Type', 'text/csv; charset=UTF-8')
        ->assertDownload('tickets-'.now()->format('Y-m-d').'.csv');

    $rows = array_map('str_getcsv', array_filter(explode("\n", trim($response->streamedContent()))));

    expect($rows)->toHaveCount(2)
        ->and($rows[0][0])->toBe('Key')
        ->and($rows[1][0])->toBe($ticket->key)
        ->and($rows[1][1])->toBe('Bug report')
        ->and($rows[1][3])->toBe('High')
        ->and($rows[1][5])->toBe('Printer offline')
        ->and($rows[1][7])->toBe('Ana Reyes');
});

test('the export takes the same filters as the list', function () {
    $admin = User::factory()->admin()->create();
    Ticket::factory()->create(['type' => 'bug_report', 'subject' => 'Printer offline']);
    Ticket::factory()->create(['type' => 'bug_report', 'subject' => 'Password reset']);

    $response = $this->actingAs($admin)->get(route('tickets.export', ['q' => 'printer']));

    $rows = array_filter(explode("\n", trim($response->streamedContent())));

    expect($rows)->toHaveCount(2);
});

test('a member cannot export tickets', function () {
    $this->actingAs(User::factory()->create())->get(route('tickets.export'))->assertForbidden();
});
