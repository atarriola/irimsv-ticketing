<?php

use App\Models\User;
use App\Models\Usertype;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('an LRMIS account is made a helpdesk administrator by its username', function () {
    Usertype::factory()->administrator()->create();
    $user = User::factory()->create(['firstname' => 'Maria', 'lastname' => 'Santos', 'username' => 'maria.santos']);

    $this->artisan('helpdesk:grant-admin', ['username' => 'maria.santos'])
        ->expectsOutputToContain('Maria Santos is now a helpdesk administrator.')
        ->assertSuccessful();

    expect($user->fresh()->isAdmin())->toBeTrue();
});

test('an administrator is returned to an LRMIS user type with the revoke option', function () {
    Usertype::factory()->create(['type_name' => 'Teacher']);
    $user = User::factory()->admin()->create(['firstname' => 'Maria', 'lastname' => 'Santos', 'username' => 'maria.santos']);

    $this->artisan('helpdesk:grant-admin', ['username' => 'maria.santos', '--revoke' => 'Teacher'])
        ->expectsOutputToContain('Maria Santos is now a Teacher and no longer administers the helpdesk.')
        ->assertSuccessful();

    $user->refresh();

    expect($user->isAdmin())->toBeFalse()
        ->and($user->usertype->type_name)->toBe('Teacher');
});

test('an unknown username fails and grants nothing', function () {
    Usertype::factory()->administrator()->create();
    User::factory()->create(['username' => 'maria.santos']);

    $this->artisan('helpdesk:grant-admin', ['username' => 'nobody'])
        ->expectsOutputToContain('No LRMIS account has the username "nobody".')
        ->assertFailed();

    expect(User::query()->administrators()->count())->toBe(0);
});

test('granting fails when LRMIS has no Administrator user type', function () {
    $user = User::factory()->create(['username' => 'maria.santos']);

    $this->artisan('helpdesk:grant-admin', ['username' => 'maria.santos'])
        ->expectsOutputToContain('LRMIS has no Administrator user type.')
        ->assertFailed();

    expect($user->fresh()->isAdmin())->toBeFalse();
});

test('revoking to an unknown user type fails, lists the types and changes nothing', function () {
    Usertype::factory()->create(['type_name' => 'Teacher', 'level' => 1]);
    $user = User::factory()->admin()->create(['username' => 'maria.santos']);

    $this->artisan('helpdesk:grant-admin', ['username' => 'maria.santos', '--revoke' => 'Janitor'])
        ->expectsOutputToContain('LRMIS has no user type named "Janitor". The types are: Administrator, Teacher.')
        ->assertFailed();

    expect($user->fresh()->isAdmin())->toBeTrue();
});
