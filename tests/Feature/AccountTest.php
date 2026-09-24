<?php

use App\Models\User;
use App\Models\Usertype;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('a guest is sent to the login page when opening account settings', function () {
    $this->get(route('account.show'))->assertRedirect(route('login'));
});

test('a user sees their LRMIS details on the account page', function () {
    $usertype = Usertype::factory()->create(['type_name' => 'School Librarian (Designated)']);
    $user = User::factory()->for($usertype)->create([
        'firstname' => 'Maria',
        'lastname' => 'Santos',
        'username' => 'maria.santos',
        'email' => 'maria@example.com',
        'contact_number' => '09171234567',
    ]);

    $this->actingAs($user)
        ->get(route('account.show'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Account/Show')
            ->where('account', [
                'name' => 'Maria Santos',
                'username' => 'maria.santos',
                'email' => 'maria@example.com',
                'contact_number' => '09171234567',
                'position' => 'School Librarian (Designated)',
                'status' => 'Active',
            ]));
});

test('account details cannot be changed from the ticketing system', function () {
    $this->actingAs(User::factory()->create())
        ->patch('/account', ['firstname' => 'Changed'])
        ->assertMethodNotAllowed();
});

test('passwords cannot be changed from the ticketing system', function () {
    $this->actingAs(User::factory()->create())
        ->put('/account/password', ['current_password' => 'password', 'password' => 'new-password', 'password_confirmation' => 'new-password'])
        ->assertNotFound();
});
