<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('helpdesk.email_notifications', true);
});

test('everyone gets emails until they turn them off', function () {
    $user = User::factory()->create();

    expect($user->wantsEmailNotifications())->toBeTrue();

    $this->actingAs($user)
        ->get(route('account.show'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('preferences.email_enabled', true)
            ->where('preferences.email_notifications', true)
            ->where('preferences.has_email', true));
});

test('the account page says so while email notifications are off for the helpdesk', function () {
    config()->set('helpdesk.email_notifications', false);

    $this->actingAs(User::factory()->create())
        ->get(route('account.show'))
        ->assertInertia(fn (Assert $page) => $page->where('preferences.email_enabled', false));
});

test('a user can turn email notifications off and on again', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from(route('account.show'))
        ->put(route('account.notifications.update'), ['email_notifications' => '0'])
        ->assertRedirect(route('account.show'))
        ->assertInertiaFlash('toast.message', 'You will only be told in the app from now on.');

    expect($user->fresh()->wantsEmailNotifications())->toBeFalse();

    $this->actingAs($user)->put(route('account.notifications.update'), ['email_notifications' => '1']);

    expect($user->fresh()->wantsEmailNotifications())->toBeTrue()
        ->and($user->preferences()->count())->toBe(1);
});

test('the setting must be a yes or a no', function () {
    $this->actingAs(User::factory()->create())
        ->put(route('account.notifications.update'), ['email_notifications' => 'maybe'])
        ->assertSessionHasErrors('email_notifications');
});

test('an account without an email address cannot get emails', function () {
    $user = User::factory()->create(['email' => '']);

    expect($user->wantsEmailNotifications())->toBeFalse();

    $this->actingAs($user)
        ->get(route('account.show'))
        ->assertInertia(fn (Assert $page) => $page->where('preferences.has_email', false));
});
