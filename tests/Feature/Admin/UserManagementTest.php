<?php

use App\Enums\UserRole;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/**
 * Build a valid account payload, optionally overriding fields.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function accountPayload(array $overrides = []): array
{
    return [
        'name' => 'Aiko Tanaka',
        'email' => 'aiko@example.com',
        'role' => 'user',
        'password' => 'correct-horse-battery',
        'password_confirmation' => 'correct-horse-battery',
        ...$overrides,
    ];
}

test('a guest is sent to the login page when opening user management', function () {
    $this->get(route('admin.users.index'))->assertRedirect(route('login'));
});

test('a regular user cannot reach user management', function (string $method, string $url) {
    $this->actingAs(User::factory()->create())->{$method}($url, accountPayload())->assertForbidden();

    expect(User::where('email', 'aiko@example.com')->exists())->toBeFalse();
})->with([
    'list' => ['get', '/admin/users'],
    'form' => ['get', '/admin/users/create'],
    'creation' => ['post', '/admin/users'],
]);

test('an admin sees the accounts with their ticket counts', function () {
    $admin = User::factory()->admin()->create(['name' => 'Zed Admin']);
    $member = User::factory()->create(['name' => 'Amy Member']);
    Ticket::factory(2)->for($member, 'requester')->create();

    $this->actingAs($admin)
        ->get(route('admin.users.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Users/Index')
            ->has('users.data', 2)
            ->where('users.data.0.name', 'Amy Member')
            ->where('users.data.0.tickets_count', 2)
            ->where('users.data.0.can.delete', true)
            ->where('users.data.1.name', 'Zed Admin')
            ->where('users.data.1.is_admin', true)
            ->where('users.data.1.can.delete', false)
            ->missing('users.data.0.password'));
});

test('an admin can create an account that can then sign in', function (string $role, UserRole $expectedRole) {
    $response = $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.users.store'), accountPayload(['role' => $role]));

    $response->assertRedirect(route('admin.users.index'))->assertInertiaFlash('toast.type', 'success');

    $user = User::firstWhere('email', 'aiko@example.com');

    expect($user->role)->toBe($expectedRole);
    expect(Hash::check('correct-horse-battery', $user->password))->toBeTrue();
})->with([
    'member' => ['user', UserRole::User],
    'administrator' => ['admin', UserRole::Admin],
]);

test('creating an account validates its details', function () {
    User::factory()->create(['email' => 'aiko@example.com']);

    $response = $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.users.store'), accountPayload(['name' => '', 'role' => 'owner', 'password_confirmation' => 'different']));

    $response->assertSessionHasErrors([
        'name' => 'The name field is required.',
        'email' => 'The email has already been taken.',
        'role' => 'The selected role is invalid.',
        'password' => 'The password field confirmation does not match.',
    ]);
});

test('an admin can update an account and promote it without touching its password', function () {
    $account = User::factory()->create();
    $originalPassword = $account->password;

    $response = $this->actingAs(User::factory()->admin()->create())->put(route('admin.users.update', $account), [
        'name' => 'Renamed User',
        'email' => 'renamed@example.com',
        'role' => 'admin',
        'password' => '',
        'password_confirmation' => '',
    ]);

    $response->assertRedirect(route('admin.users.index'));

    $account->refresh();

    expect($account->name)->toBe('Renamed User');
    expect($account->email)->toBe('renamed@example.com');
    expect($account->isAdmin())->toBeTrue();
    expect($account->password)->toBe($originalPassword);
});

test('an admin can reset an account password', function () {
    $account = User::factory()->create();

    $this->actingAs(User::factory()->admin()->create())->put(route('admin.users.update', $account), [
        'name' => $account->name,
        'email' => $account->email,
        'role' => 'user',
        'password' => 'a-brand-new-password',
        'password_confirmation' => 'a-brand-new-password',
    ]);

    expect(Hash::check('a-brand-new-password', $account->fresh()->password))->toBeTrue();
});

test('an admin cannot demote themselves', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->put(route('admin.users.update', $admin), ['name' => $admin->name, 'email' => $admin->email, 'role' => 'user'])
        ->assertSessionHasErrors(['role' => 'You cannot change your own role.']);

    expect($admin->fresh()->isAdmin())->toBeTrue();
});

test('an admin can delete another account but not their own', function () {
    $admin = User::factory()->admin()->create();
    $account = User::factory()->create();

    $this->actingAs($admin)->delete(route('admin.users.destroy', $account))->assertRedirect(route('admin.users.index'));
    $this->actingAs($admin)->delete(route('admin.users.destroy', $admin))->assertForbidden();

    expect(User::pluck('id')->all())->toBe([$admin->id]);
});

test('a regular user cannot update or delete an account', function (string $method) {
    $account = User::factory()->create(['name' => 'Original Name']);

    $this->actingAs(User::factory()->create())
        ->{$method}("/admin/users/{$account->id}", ['name' => 'Hijacked', 'email' => $account->email, 'role' => 'admin'])
        ->assertForbidden();

    expect($account->fresh()->name)->toBe('Original Name');
})->with(['put', 'delete']);
