<?php

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

uses(RefreshDatabase::class);

test('responses carry the browser security headers', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
        ->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()')
        ->assertHeaderMissing('Strict-Transport-Security');
});

test('strict transport security is only sent over https', function () {
    $this->get('https://localhost/')
        ->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
});

test('a session is signed out once its password has been changed elsewhere', function () {
    $user = User::factory()->create();

    $this->post(route('login.store'), ['username' => $user->username, 'password' => 'password']);
    $this->get(route('dashboard'))->assertOk();

    $user->forceFill(['password' => 'reset-by-an-administrator'])->save();
    $this->app['auth']->forgetGuards();

    $this->get(route('dashboard'))->assertRedirect(route('login'));
    $this->assertGuest();
});

test('posting is limited to sixty requests a minute for each user', function () {
    $user = User::factory()->create();
    $ticket = Ticket::factory()->for($user, 'requester')->create();

    foreach (range(1, 60) as $attempt) {
        $this->actingAs($user)
            ->postJson(route('tickets.comments.store', $ticket), ['body' => 'Any update on this?'])
            ->assertCreated();
    }

    $this->actingAs($user)
        ->postJson(route('tickets.comments.store', $ticket), ['body' => 'Any update on this?'])
        ->assertTooManyRequests();

    $this->actingAs(User::factory()->admin()->create())
        ->postJson(route('tickets.comments.store', $ticket), ['body' => 'We are looking into it.'])
        ->assertCreated();
});

test('production only accepts a long mixed-case password with a number', function (string $password, bool $isAccepted) {
    $this->app->detectEnvironment(fn (): string => 'production');

    $validator = Validator::make(['password' => $password], ['password' => Password::defaults()]);

    expect($validator->passes())->toBe($isAccepted);
})->with([
    'too short' => ['Short-Pass1', false],
    'no capital letter' => ['a-long-password-with-1-number', false],
    'no number' => ['A-Long-Password-Without-Digits', false],
    'long, mixed case and a number' => ['A-Long-Password-With-1-Number', true],
]);
