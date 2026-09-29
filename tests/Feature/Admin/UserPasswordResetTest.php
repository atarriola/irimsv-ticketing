<?php

use App\Models\User;
use App\Models\Usertype;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('a guest is sent to the login page when opening a password reset', function () {
    $this->get(route('admin.users.password.edit', User::factory()->create()))->assertRedirect(route('login'));
});

test('a member cannot reset passwords', function () {
    $member = User::factory()->create();
    $other = User::factory()->create();

    $this->actingAs($member)->get(route('admin.users.password.edit', $other))->assertForbidden();
    $this->actingAs($member)
        ->put(route('admin.users.password.update', $other), ['password' => 'new-password', 'password_confirmation' => 'new-password'])
        ->assertForbidden();

    expect(Hash::check('password', $other->fresh()->password))->toBeTrue();
});

test('an administrator sees whose password they are resetting', function () {
    $teacher = Usertype::factory()->create(['type_name' => 'Teacher']);
    $user = User::factory()->for($teacher)->create(['firstname' => 'Maria', 'lastname' => 'Santos', 'username' => 'maria.santos', 'email' => 'maria@example.com']);

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.users.password.edit', $user))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Users/Password')
            ->where('account', [
                'id' => $user->id,
                'name' => 'Maria Santos',
                'username' => 'maria.santos',
                'email' => 'maria@example.com',
                'position' => 'Teacher',
            ]));
});

test('an administrator resets a password and signs the account out of remembered browsers', function () {
    $user = User::factory()->create(['remember_token' => 'old-token']);

    $this->actingAs(User::factory()->admin()->create())
        ->put(route('admin.users.password.update', $user), ['password' => 'new-password', 'password_confirmation' => 'new-password'])
        ->assertRedirect(route('admin.users.index'))
        ->assertSessionHasNoErrors();

    $user->refresh();

    expect(Hash::check('new-password', $user->password))->toBeTrue()
        ->and($user->remember_token)->not->toBe('old-token');
});

test('an administrator cannot reset their own password this way', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->get(route('admin.users.password.edit', $admin))->assertForbidden();
    $this->actingAs($admin)
        ->put(route('admin.users.password.update', $admin), ['password' => 'new-password', 'password_confirmation' => 'new-password'])
        ->assertForbidden();

    expect(Hash::check('password', $admin->fresh()->password))->toBeTrue();
});

test('the new password must be confirmed', function () {
    $user = User::factory()->create();

    $this->actingAs(User::factory()->admin()->create())
        ->put(route('admin.users.password.update', $user), ['password' => 'new-password', 'password_confirmation' => 'different'])
        ->assertSessionHasErrors(['password' => 'The password field confirmation does not match.']);

    expect(Hash::check('password', $user->fresh()->password))->toBeTrue();
});

test('an unknown account cannot have its password reset', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.users.password.edit', ['user' => (string) Str::uuid()]))
        ->assertNotFound();
});
