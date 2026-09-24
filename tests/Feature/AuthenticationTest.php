<?php

use App\Models\User;
use App\Models\Usertype;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('the old login address sends guests to the index page', function () {
    $this->get('/login')->assertRedirect('/');
});

test('registration is not available', function (string $method) {
    $this->{$method}('/register')->assertNotFound();
})->with(['get', 'post']);

test('a user can log in with their LRMIS username and password', function () {
    $user = User::factory()->create(['username' => 'maria.santos']);

    $response = $this->post(route('login.store'), [
        'username' => 'maria.santos',
        'password' => 'password',
    ]);

    $response->assertRedirect(route('dashboard'));
    $this->assertAuthenticatedAs($user);
});

test('the username must match exactly as it is in LRMIS', function () {
    User::factory()->create(['username' => 'maria.santos']);

    $response = $this->post(route('login.store'), [
        'username' => 'Maria.Santos',
        'password' => 'password',
    ]);

    $response->assertSessionHasErrors(['username' => 'These credentials do not match our records.']);
    $this->assertGuest();
});

test('a user cannot log in with a wrong password', function () {
    $user = User::factory()->create();

    $response = $this->post(route('login.store'), [
        'username' => $user->username,
        'password' => 'wrong-password',
    ]);

    $response->assertSessionHasErrors(['username' => 'These credentials do not match our records.']);
    $this->assertGuest();
});

test('a user whose LRMIS account is not active cannot log in', function (string $state) {
    $user = User::factory()->{$state}()->create();

    $response = $this->post(route('login.store'), [
        'username' => $user->username,
        'password' => 'password',
    ]);

    $response->assertSessionHasErrors(['username' => 'Your LRMIS account is not active, so you cannot sign in yet.']);
    $this->assertGuest();
})->with(['pending', 'deactivated']);

test('login requires a username and a password', function () {
    $response = $this->post(route('login.store'), []);

    $response->assertSessionHasErrors([
        'username' => 'The username field is required.',
        'password' => 'The password field is required.',
    ]);
});

test('login is locked after five failed attempts even with the right password', function () {
    $user = User::factory()->create();

    foreach (range(1, 5) as $attempt) {
        $this->post(route('login.store'), ['username' => $user->username, 'password' => 'wrong-password']);
    }

    $response = $this->post(route('login.store'), [
        'username' => $user->username,
        'password' => 'password',
    ]);

    $response->assertSessionHasErrors('username');
    expect(session('errors')->first('username'))->toStartWith('Too many login attempts.');
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

test('pages receive the signed-in user with their position and photo but without sensitive fields', function () {
    config()->set('filesystems.disks.public.url', 'https://lrmis.test/storage');
    $admin = User::factory()->for(Usertype::factory()->create(['type_name' => 'Information Technology Officer']))->admin()->create(['photo' => 'admin.jpg']);

    $this->actingAs($admin)
        ->get(route('tickets.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.user.id', $admin->id)
            ->where('auth.user.position', 'Information Technology Officer')
            ->where('auth.user.photo_url', 'https://lrmis.test/storage/user_pic/admin.jpg')
            ->where('auth.user.is_admin', true)
            ->missing('auth.user.password')
            ->missing('auth.user.remember_token'));
});

test('pages receive no user for guests', function () {
    $this->get(route('login'))
        ->assertInertia(fn (Assert $page) => $page->where('auth.user', null));
});
