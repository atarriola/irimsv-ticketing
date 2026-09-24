<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('an admin can :ability another account', function (string $ability) {
    $admin = User::factory()->admin()->create();
    $account = User::factory()->create();

    expect($admin->can($ability, $account))->toBeTrue();
})->with(['update', 'changeRole', 'delete']);

test('an admin can list and create accounts', function (string $ability) {
    expect(User::factory()->admin()->create()->can($ability, User::class))->toBeTrue();
})->with(['viewAny', 'create']);

test('an admin can edit their own account but cannot :ability it', function (string $ability) {
    $admin = User::factory()->admin()->create();

    expect($admin->can('update', $admin))->toBeTrue();
    expect($admin->can($ability, $admin))->toBeFalse();
})->with(['changeRole', 'delete']);

test('a regular user cannot :ability accounts', function (string $ability) {
    $user = User::factory()->create();
    $account = User::factory()->create();

    expect($user->can($ability, in_array($ability, ['viewAny', 'create']) ? User::class : $account))->toBeFalse();
})->with(['viewAny', 'create', 'update', 'changeRole', 'delete']);
