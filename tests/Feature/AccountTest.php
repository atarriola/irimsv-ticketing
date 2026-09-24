<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('a guest is sent to the login page when opening account settings', function () {
    $this->get(route('account.edit'))->assertRedirect(route('login'));
});

test('a user sees their own details on the account page', function () {
    $user = User::factory()->create(['name' => 'Maria Santos', 'email' => 'maria@example.com']);

    $this->actingAs($user)
        ->get(route('account.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Account/Edit')
            ->where('account', ['name' => 'Maria Santos', 'email' => 'maria@example.com']));
});

test('a user can update their name and email but not their role', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch(route('account.update'), ['name' => 'New Name', 'email' => 'new@example.com', 'role' => 'admin'])
        ->assertRedirect(route('account.edit'))
        ->assertInertiaFlash('toast.type', 'success');

    $user->refresh();

    expect($user->name)->toBe('New Name');
    expect($user->email)->toBe('new@example.com');
    expect($user->isAdmin())->toBeFalse();
});

test('a user cannot take an email that belongs to someone else', function () {
    User::factory()->create(['email' => 'taken@example.com']);
    $user = User::factory()->create(['email' => 'mine@example.com']);

    $this->actingAs($user)
        ->patch(route('account.update'), ['name' => $user->name, 'email' => 'taken@example.com'])
        ->assertSessionHasErrors(['email' => 'The email has already been taken.']);

    expect($user->fresh()->email)->toBe('mine@example.com');
});

test('a user can change their password with their current one', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->put(route('account.password.update'), [
            'current_password' => 'password',
            'password' => 'a-brand-new-password',
            'password_confirmation' => 'a-brand-new-password',
        ])
        ->assertRedirect(route('account.edit'));

    expect(Hash::check('a-brand-new-password', $user->fresh()->password))->toBeTrue();
});

test('a password change is refused when :case', function (array $payload, string $field, string $message) {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->put(route('account.password.update'), $payload)
        ->assertSessionHasErrors([$field => $message]);

    expect(Hash::check('password', $user->fresh()->password))->toBeTrue();
})->with([
    'the current password is wrong' => [
        ['current_password' => 'not-my-password', 'password' => 'a-brand-new-password', 'password_confirmation' => 'a-brand-new-password'],
        'current_password',
        'The password is incorrect.',
    ],
    'the confirmation does not match' => [
        ['current_password' => 'password', 'password' => 'a-brand-new-password', 'password_confirmation' => 'something-else'],
        'password',
        'The password field confirmation does not match.',
    ],
]);
