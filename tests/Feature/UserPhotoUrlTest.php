<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('filesystems.disks.public.url', 'https://lrmis.test/storage');
});

test('a bare photo filename resolves to the LRMIS user_pic folder', function () {
    $user = User::factory()->make(['photo' => 'jane_doe.jpg']);

    expect($user->photo_url)->toBe('https://lrmis.test/storage/user_pic/jane_doe.jpg');
});

test('a folder-qualified photo path is kept as stored', function () {
    $user = User::factory()->make(['photo' => '/user_pic/archive/jane_doe.jpg']);

    expect($user->photo_url)->toBe('https://lrmis.test/storage/user_pic/archive/jane_doe.jpg');
});

test('an absolute photo URL is returned untouched', function () {
    $user = User::factory()->make(['photo' => 'https://cdn.example.com/jane.jpg']);

    expect($user->photo_url)->toBe('https://cdn.example.com/jane.jpg');
});

test('users without a photo have no photo URL', function (?string $photo) {
    $user = User::factory()->make(['photo' => $photo]);

    expect($user->photo_url)->toBeNull();
})->with([null, '', '   ']);
