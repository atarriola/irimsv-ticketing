<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('the old login address sends guests to the index page', function () {
    $this->get('/login')->assertRedirect('/');
});

test('registration is not available', function (string $method) {
    $this->{$method}('/register')->assertNotFound();
})->with(['get', 'post']);

test('a user can log in with valid credentials', function () {
    $user = User::factory()->create();

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response->assertRedirect(route('dashboard'));
    $this->assertAuthenticatedAs($user);
});

test('a user cannot log in with a wrong password', function () {
    $user = User::factory()->create();

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $response->assertSessionHasErrors(['email' => 'These credentials do not match our records.']);
    $this->assertGuest();
});

test('login requires an email and a password', function () {
    $response = $this->post(route('login.store'), []);

    $response->assertSessionHasErrors([
        'email' => 'The email field is required.',
        'password' => 'The password field is required.',
    ]);
});

test('login is locked after five failed attempts even with the right password', function () {
    $user = User::factory()->create();

    foreach (range(1, 5) as $attempt) {
        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'wrong-password']);
    }

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response->assertSessionHasErrors('email');
    expect(session('errors')->first('email'))->toStartWith('Too many login attempts.');
    $this->assertGuest();
});

test('a signed-in user is sent to the dashboard instead of the login page', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('login'))
        ->assertRedirect(route('dashboard'));
});

test('a user can log out', function () {
    $response = $this->actingAs(User::factory()->create())->post(route('logout'));

    $response->assertRedirect(route('login'));
    $this->assertGuest();
});

test('a guest is sent to the login page when opening tickets', function () {
    $this->get(route('tickets.index'))->assertRedirect(route('login'));
});

test('pages receive the signed-in user without sensitive fields', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('tickets.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.user.id', $admin->id)
            ->where('auth.user.is_admin', true)
            ->missing('auth.user.password')
            ->missing('auth.user.remember_token'));
});

test('pages receive no user for guests', function () {
    $this->get(route('login'))
        ->assertInertia(fn (Assert $page) => $page->where('auth.user', null));
});
