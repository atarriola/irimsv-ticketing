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
})->with(['view', 'update', 'delete', 'changeStatus', 'comment']);

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
