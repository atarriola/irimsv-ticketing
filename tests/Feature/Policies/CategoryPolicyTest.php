<?php

use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('any user can list categories', function () {
    $user = User::factory()->create();

    expect($user->can('viewAny', Category::class))->toBeTrue();
});

test('an admin can manage categories', function () {
    $admin = User::factory()->admin()->create();
    $category = Category::factory()->create();

    expect($admin->can('create', Category::class))->toBeTrue();
    expect($admin->can('update', $category))->toBeTrue();
    expect($admin->can('delete', $category))->toBeTrue();
});

test('a regular user cannot manage categories', function () {
    $user = User::factory()->create();
    $category = Category::factory()->create();

    expect($user->can('create', Category::class))->toBeFalse();
    expect($user->can('update', $category))->toBeFalse();
    expect($user->can('delete', $category))->toBeFalse();
});
