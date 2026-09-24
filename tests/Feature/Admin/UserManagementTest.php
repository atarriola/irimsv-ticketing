<?php

use App\Models\Ticket;
use App\Models\User;
use App\Models\Usertype;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('a guest is sent to the login page when opening user management', function () {
    $this->get(route('admin.users.index'))->assertRedirect(route('login'));
});

test('a regular user cannot reach user management', function (string $method, string $url) {
    $account = User::factory()->create();

    $this->actingAs(User::factory()->create())
        ->{$method}(str_replace('{user}', $account->id, $url), ['role' => 'admin'])
        ->assertForbidden();

    expect($account->fresh()->isAdmin())->toBeFalse();
})->with([
    'list' => ['get', '/admin/users'],
    'role change' => ['patch', '/admin/users/{user}/role'],
]);

test('an admin sees the LRMIS accounts with their position, status, role and ticket count', function () {
    $admin = User::factory()->admin()->create(['firstname' => 'Zed', 'lastname' => 'Young']);
    $teacher = Usertype::factory()->create(['type_name' => 'Teacher']);
    $member = User::factory()->for($teacher)->deactivated()->create(['firstname' => 'Amy', 'lastname' => 'Brown', 'username' => 'amy.brown']);
    Ticket::factory(2)->for($member, 'requester')->create();

    $this->actingAs($admin)
        ->get(route('admin.users.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Users/Index')
            ->where('filters.q', '')
            ->has('users.data', 2)
            ->where('users.data.0.name', 'Amy Brown')
            ->where('users.data.0.username', 'amy.brown')
            ->where('users.data.0.position', 'Teacher')
            ->where('users.data.0.status', 'Deactivated')
            ->where('users.data.0.is_active', false)
            ->where('users.data.0.is_admin', false)
            ->where('users.data.0.tickets_count', 2)
            ->where('users.data.0.can.changeRole', true)
            ->where('users.data.1.name', 'Zed Young')
            ->where('users.data.1.is_admin', true)
            ->where('users.data.1.can.changeRole', false)
            ->missing('users.data.0.password'));
});

test('an admin can search the accounts', function (string $term) {
    $match = User::factory()->create(['firstname' => 'Maria', 'lastname' => 'Santos', 'username' => 'msantos01', 'email' => 'maria.santos@deped.gov.ph']);
    User::factory()->create(['firstname' => 'Juan', 'lastname' => 'Cruz', 'username' => 'jcruz', 'email' => 'juan.cruz@deped.gov.ph']);
    $admin = User::factory()->admin()->create(['firstname' => 'Ada', 'lastname' => 'Admin', 'username' => 'ada.admin', 'email' => 'ada@example.com']);

    $this->actingAs($admin)
        ->get(route('admin.users.index', ['q' => $term]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.q', $term)
            ->has('users.data', 1)
            ->where('users.data.0.id', $match->id));
})->with([
    'by surname' => 'santos',
    'by username in any case' => 'MSANTOS',
    'by email' => 'maria.santos@',
]);

test('an admin can make an account an administrator', function () {
    $account = User::factory()->create(['firstname' => 'Amy', 'lastname' => 'Brown']);

    $this->actingAs(User::factory()->admin()->create())
        ->from(route('admin.users.index'))
        ->patch(route('admin.users.role.update', $account), ['role' => 'admin'])
        ->assertRedirect(route('admin.users.index'))
        ->assertInertiaFlash('toast.message', 'Amy Brown is now an administrator.');

    $this->assertDatabaseHas('user_roles', ['user_id' => $account->id, 'role' => 'admin']);
    expect($account->fresh()->isAdmin())->toBeTrue();
});

test('an admin can make an administrator a member again', function () {
    $account = User::factory()->admin()->create(['firstname' => 'Amy', 'lastname' => 'Brown']);

    $this->actingAs(User::factory()->admin()->create())
        ->from(route('admin.users.index'))
        ->patch(route('admin.users.role.update', $account), ['role' => 'user'])
        ->assertRedirect(route('admin.users.index'))
        ->assertInertiaFlash('toast.message', 'Amy Brown is now a member.');

    $this->assertDatabaseHas('user_roles', ['user_id' => $account->id, 'role' => 'user']);
    expect($account->fresh()->isAdmin())->toBeFalse();
});

test('an admin cannot change their own role', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->patch(route('admin.users.role.update', $admin), ['role' => 'user'])
        ->assertForbidden();

    expect($admin->fresh()->isAdmin())->toBeTrue();
});

test('the role must be a known helpdesk role', function () {
    $account = User::factory()->create();

    $this->actingAs(User::factory()->admin()->create())
        ->patch(route('admin.users.role.update', $account), ['role' => 'owner'])
        ->assertSessionHasErrors(['role' => 'The selected role is invalid.']);

    expect($account->fresh()->isAdmin())->toBeFalse();
});

test('a role change for an unknown account is not found', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->patch('/admin/users/'.Str::uuid().'/role', ['role' => 'admin'])
        ->assertNotFound();
});
