<?php

use App\Models\User;
use App\Models\Usertype;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
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
            ])
            ->where('can.update', false));
});

test('a member cannot edit their details, which LRMIS manages', function () {
    $member = User::factory()->create(['firstname' => 'Maria', 'lastname' => 'Santos', 'email' => 'maria@example.com']);

    $this->actingAs($member)->get(route('account.edit'))->assertForbidden();
    $this->actingAs($member)
        ->patch(route('account.update'), ['firstname' => 'Changed', 'lastname' => 'Santos', 'email' => 'maria@example.com'])
        ->assertForbidden();

    expect($member->fresh()->firstname)->toBe('Maria');
});

test('a member cannot change their password from the ticketing system', function () {
    $member = User::factory()->create();

    $this->actingAs($member)
        ->put(route('account.password.update'), ['current_password' => 'password', 'password' => 'new-password', 'password_confirmation' => 'new-password'])
        ->assertForbidden();

    expect(Hash::check('password', $member->fresh()->password))->toBeTrue();
});

test('an administrator is offered to edit their account', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get(route('account.show'))
        ->assertInertia(fn (Assert $page) => $page->where('can.update', true));
});

test('an administrator sees their details prefilled on the edit page', function () {
    $admin = User::factory()->admin()->create([
        'firstname' => 'Helpdesk',
        'middlename' => null,
        'lastname' => 'Administrator',
        'extension_name' => null,
        'username' => 'helpdesk.admin',
        'email' => 'admin@example.com',
        'contact_number' => '',
    ]);

    $this->actingAs($admin)
        ->get(route('account.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Account/Edit')
            ->where('account', [
                'firstname' => 'Helpdesk',
                'middlename' => null,
                'lastname' => 'Administrator',
                'extension_name' => null,
                'username' => 'helpdesk.admin',
                'email' => 'admin@example.com',
                'contact_number' => '',
            ]));
});

test('an administrator updates their own details but not their username', function () {
    $admin = User::factory()->admin()->create(['username' => 'helpdesk.admin']);

    $this->actingAs($admin)
        ->from(route('account.edit'))
        ->patch(route('account.update'), [
            'firstname' => 'Juan',
            'middlename' => 'Dela',
            'lastname' => 'Cruz',
            'extension_name' => 'Jr.',
            'email' => 'juan.cruz@deped.gov.ph',
            'contact_number' => '',
            'username' => 'someone.else',
        ])
        ->assertRedirect(route('account.edit'))
        ->assertSessionHasNoErrors();

    $admin->refresh();

    expect($admin->name)->toBe('Juan Cruz Jr.')
        ->and($admin->middlename)->toBe('Dela')
        ->and($admin->email)->toBe('juan.cruz@deped.gov.ph')
        ->and($admin->contact_number)->toBe('')
        ->and($admin->username)->toBe('helpdesk.admin');
});

test('an administrator may keep their current email address', function () {
    $admin = User::factory()->admin()->create(['email' => 'admin@example.com']);

    $this->actingAs($admin)
        ->patch(route('account.update'), ['firstname' => 'Helpdesk', 'lastname' => 'Administrator', 'email' => 'admin@example.com'])
        ->assertSessionHasNoErrors();
});

test('an administrator cannot take an email address another LRMIS account uses', function () {
    User::factory()->create(['email' => 'taken@example.com']);
    $admin = User::factory()->admin()->create(['email' => 'admin@example.com']);

    $this->actingAs($admin)
        ->patch(route('account.update'), ['firstname' => 'Helpdesk', 'lastname' => 'Administrator', 'email' => 'taken@example.com'])
        ->assertSessionHasErrors(['email' => 'The email has already been taken.']);

    expect($admin->fresh()->email)->toBe('admin@example.com');
});

test('the account details must be complete', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->patch(route('account.update'), [])
        ->assertSessionHasErrors(['firstname', 'lastname', 'email']);
});

test('an administrator changes their password with their current password and stays signed in', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->from(route('account.edit'))
        ->put(route('account.password.update'), ['current_password' => 'password', 'password' => 'new-password', 'password_confirmation' => 'new-password'])
        ->assertRedirect(route('account.edit'))
        ->assertSessionHasNoErrors();

    expect(Hash::check('new-password', $admin->fresh()->password))->toBeTrue();

    $this->get(route('account.edit'))->assertOk();
});

test('the current password must be right to change it', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->put(route('account.password.update'), ['current_password' => 'wrong', 'password' => 'new-password', 'password_confirmation' => 'new-password'])
        ->assertSessionHasErrors(['current_password' => 'The password is incorrect.']);

    expect(Hash::check('password', $admin->fresh()->password))->toBeTrue();
});

test('the new password must be confirmed', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->put(route('account.password.update'), ['current_password' => 'password', 'password' => 'new-password', 'password_confirmation' => 'different'])
        ->assertSessionHasErrors(['password' => 'The password field confirmation does not match.']);

    expect(Hash::check('password', $admin->fresh()->password))->toBeTrue();
});
