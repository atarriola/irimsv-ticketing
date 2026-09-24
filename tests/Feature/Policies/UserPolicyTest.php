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
