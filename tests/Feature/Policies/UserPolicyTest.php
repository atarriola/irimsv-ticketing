<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('an admin can list accounts', function () {
    expect(User::factory()->admin()->create()->can('viewAny', User::class))->toBeTrue();
});

test('an admin can change the role of another account but not their own', function () {
    $admin = User::factory()->admin()->create();
    $account = User::factory()->create();

    expect($admin->can('changeRole', $account))->toBeTrue();
    expect($admin->can('changeRole', $admin))->toBeFalse();
});

test('a regular user cannot :ability accounts', function (string $ability) {
    $user = User::factory()->create();
    $account = User::factory()->create();

    expect($user->can($ability, $ability === 'viewAny' ? User::class : $account))->toBeFalse();
})->with(['viewAny', 'changeRole']);
