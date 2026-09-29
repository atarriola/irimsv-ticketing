<?php

use App\Enums\UserStatus;
use App\Models\User;
use App\Models\Usertype;
use Database\Seeders\AdministratorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'helpdesk.administrator.username' => 'helpdesk.admin',
        'helpdesk.administrator.password' => 'seeded-secret',
        'helpdesk.administrator.email' => 'helpdesk@example.com',
    ]);
});

test('the configured account is seeded on the existing LRMIS Administrator type', function () {
    $administrator = Usertype::factory()->administrator()->create();

    $this->seed(AdministratorSeeder::class);

    $user = User::query()->where('username', 'helpdesk.admin')->sole();

    expect($user->isAdmin())->toBeTrue()
        ->and($user->usertype_id)->toBe($administrator->id)
        ->and($user->email)->toBe('helpdesk@example.com')
        ->and($user->status)->toBe(UserStatus::Active)
        ->and(Hash::check('seeded-secret', $user->password))->toBeTrue()
        ->and(Usertype::query()->count())->toBe(1);
});

test('the Administrator type is created when the database has none', function () {
    $this->seed(AdministratorSeeder::class);

    expect(Usertype::query()->administrator()->count())->toBe(1)
        ->and(User::query()->administrators()->count())->toBe(1);
});

test('the seeded administrator can sign in', function () {
    $this->seed(AdministratorSeeder::class);

    $this->post(route('login.store'), ['username' => 'helpdesk.admin', 'password' => 'seeded-secret'])
        ->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs(User::query()->where('username', 'helpdesk.admin')->sole());
});

test('an existing account with the configured username is neither replaced nor promoted', function () {
    Usertype::factory()->administrator()->create();
    $existing = User::factory()->create(['username' => 'helpdesk.admin', 'password' => 'their-own-password']);

    $this->seed(AdministratorSeeder::class);

    $existing->refresh();

    expect(User::query()->where('username', 'helpdesk.admin')->count())->toBe(1)
        ->and($existing->isAdmin())->toBeFalse()
        ->and(Hash::check('their-own-password', $existing->password))->toBeTrue();
});

test('seeding twice creates one administrator', function () {
    $this->seed(AdministratorSeeder::class);
    $this->seed(AdministratorSeeder::class);

    expect(User::query()->administrators()->count())->toBe(1);
});

test('the database seeder seeds the administrator', function () {
    $this->seed();

    expect(User::query()->administrators()->count())->toBe(1);
});
