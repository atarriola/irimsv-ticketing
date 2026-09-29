<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('an administrator can list accounts', function () {
    expect(User::factory()->admin()->create()->can('viewAny', User::class))->toBeTrue();
});

test('a member cannot list accounts', function () {
    expect(User::factory()->create()->can('viewAny', User::class))->toBeFalse();
});

test('an administrator can edit their own account but not another account', function () {
    $admin = User::factory()->admin()->create();
    $other = User::factory()->create();

    expect($admin->can('update', $admin))->toBeTrue()
        ->and($admin->can('update', $other))->toBeFalse();
});

test('a member cannot edit any account, including their own', function () {
    $member = User::factory()->create();

    expect($member->can('update', $member))->toBeFalse();
});

test('an administrator can reset the password of any account but their own', function () {
    $admin = User::factory()->admin()->create();
    $otherAdmin = User::factory()->admin()->create();
    $member = User::factory()->create();

    expect($admin->can('resetPassword', $member))->toBeTrue()
        ->and($admin->can('resetPassword', $otherAdmin))->toBeTrue()
        ->and($admin->can('resetPassword', $admin))->toBeFalse();
});

test('a member cannot reset any password', function () {
    $member = User::factory()->create();
    $other = User::factory()->create();

    expect($member->can('resetPassword', $other))->toBeFalse()
        ->and($member->can('resetPassword', $member))->toBeFalse();
});
