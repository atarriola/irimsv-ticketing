<?php

use App\Models\Ticket;
use App\Models\User;
use App\Models\Usertype;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('a guest is sent to the login page when opening user management', function () {
    $this->get(route('admin.users.index'))->assertRedirect(route('login'));
});

test('a member cannot reach user management', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('admin.users.index'))
        ->assertForbidden();
});

test('an administrator sees the LRMIS accounts with their position, status, role and ticket count', function () {
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
            ->where('users.data.1.name', 'Zed Young')
            ->where('users.data.1.position', 'Administrator')
            ->where('users.data.1.is_admin', true)
            ->missing('users.data.0.password'));
});

test('an administrator can search the accounts', function (string $term) {
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
