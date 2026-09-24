<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('an LRMIS account is made a helpdesk administrator by its username', function () {
    $user = User::factory()->create(['firstname' => 'Maria', 'lastname' => 'Santos', 'username' => 'maria.santos']);

    $this->artisan('helpdesk:grant-admin', ['username' => 'maria.santos'])
        ->expectsOutputToContain('Maria Santos is now a helpdesk administrator.')
        ->assertSuccessful();

    expect($user->fresh()->isAdmin())->toBeTrue();
});

test('an administrator is made a member again with the revoke option', function () {
    $user = User::factory()->admin()->create(['firstname' => 'Maria', 'lastname' => 'Santos', 'username' => 'maria.santos']);

    $this->artisan('helpdesk:grant-admin', ['username' => 'maria.santos', '--revoke' => true])
        ->expectsOutputToContain('Maria Santos is now a member.')
        ->assertSuccessful();

    expect($user->fresh()->isAdmin())->toBeFalse();
});

test('an unknown username fails and grants nothing', function () {
    $this->artisan('helpdesk:grant-admin', ['username' => 'nobody'])
        ->expectsOutputToContain('No LRMIS account has the username "nobody".')
        ->assertFailed();

    $this->assertDatabaseCount('user_roles', 0);
});
