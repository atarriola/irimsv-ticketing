<?php

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('an admin can :ability any open ticket', function (string $ability) {
    $admin = User::factory()->admin()->create();
    $ticket = Ticket::factory()->create();

    expect($admin->can($ability, $ticket))->toBeTrue();
})->with(['view', 'update', 'delete', 'changeStatus', 'comment']);

test('a requester can :ability their own open ticket', function (string $ability) {
    $requester = User::factory()->create();
    $ticket = Ticket::factory()->for($requester, 'requester')->create();

    expect($requester->can($ability, $ticket))->toBeTrue();
})->with(['view', 'update', 'comment']);

test('a requester cannot :ability their own ticket', function (string $ability) {
    $requester = User::factory()->create();
    $ticket = Ticket::factory()->for($requester, 'requester')->create();

    expect($requester->can($ability, $ticket))->toBeFalse();
})->with(['delete', 'changeStatus']);

test('a user cannot :ability a ticket raised by someone else', function (string $ability) {
    $otherUser = User::factory()->create();
    $ticket = Ticket::factory()->create();

    expect($otherUser->can($ability, $ticket))->toBeFalse();
})->with(['update', 'delete', 'changeStatus']);

test('a user can :ability a shared ticket raised by someone else', function (string $ability) {
    $otherUser = User::factory()->create();
    $ticket = Ticket::factory()->shared()->create();

    expect($otherUser->can($ability, $ticket))->toBeTrue();
})->with(['view', 'comment', 'watch']);

test('a user cannot :ability a private ticket raised by someone else', function (string $ability) {
    $otherUser = User::factory()->create();
    $ticket = Ticket::factory()->create();

    expect($otherUser->can($ability, $ticket))->toBeFalse();
})->with(['view', 'comment', 'watch']);

test('a requester cannot watch their own ticket, since they are always told about it', function () {
    $requester = User::factory()->create();
    $ticket = Ticket::factory()->for($requester, 'requester')->shared()->create();

    expect($requester->can('watch', $ticket))->toBeFalse();
});

test('only an admin can :ability a ticket', function (string $ability) {
    $requester = User::factory()->create();
    $ticket = Ticket::factory()->for($requester, 'requester')->featureRequest()->create();

    expect(User::factory()->admin()->create()->can($ability, $ticket))->toBeTrue();
    expect($requester->can($ability, $ticket))->toBeFalse();
})->with(['changePriority', 'addInternalNote', 'linkRelease']);

test('a release can only be linked to a feature request', function () {
    $admin = User::factory()->admin()->create();

    expect($admin->can('linkRelease', Ticket::factory()->create(['type' => 'bug_report'])))->toBeFalse();
});

test('a deleted ticket can only be seen and restored by an admin', function () {
    $requester = User::factory()->create();
    $ticket = Ticket::factory()->for($requester, 'requester')->create();
    $ticket->delete();
    $admin = User::factory()->admin()->create();

    expect($admin->can('view', $ticket))->toBeTrue()
        ->and($admin->can('restore', $ticket))->toBeTrue()
        ->and($admin->can('update', $ticket))->toBeFalse()
        ->and($admin->can('comment', $ticket))->toBeFalse()
        ->and($requester->can('view', $ticket))->toBeFalse();
});

test('a requester can rate a ticket only once it is done', function () {
    $requester = User::factory()->create();

    expect($requester->can('rate', Ticket::factory()->for($requester, 'requester')->create()))->toBeFalse()
        ->and($requester->can('rate', Ticket::factory()->for($requester, 'requester')->resolved()->create()))->toBeTrue()
        ->and($requester->can('rate', Ticket::factory()->for($requester, 'requester')->closed()->create()))->toBeTrue()
        ->and(User::factory()->create()->can('rate', Ticket::factory()->shared()->closed()->create()))->toBeFalse();
});

test('any user can list and raise tickets', function (string $ability) {
    $user = User::factory()->create();

    expect($user->can($ability, Ticket::class))->toBeTrue();
})->with(['viewAny', 'create']);

test('a requester cannot edit their ticket once it is resolved', function () {
    $requester = User::factory()->create();
    $ticket = Ticket::factory()->for($requester, 'requester')->resolved()->create();

    expect($requester->can('update', $ticket))->toBeFalse();
});

test('a requester can still comment on their resolved ticket', function () {
    $requester = User::factory()->create();
    $ticket = Ticket::factory()->for($requester, 'requester')->resolved()->create();

    expect($requester->can('comment', $ticket))->toBeTrue();
});

test('nobody can comment on a closed ticket', function () {
    $requester = User::factory()->create();
    $admin = User::factory()->admin()->create();
    $ticket = Ticket::factory()->for($requester, 'requester')->closed()->create();

    expect($requester->can('comment', $ticket))->toBeFalse();
    expect($admin->can('comment', $ticket))->toBeFalse();
});
