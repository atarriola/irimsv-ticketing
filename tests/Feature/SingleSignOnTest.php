<?php

use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['helpdesk.sso.secret' => 'shared-secret']);
});

/**
 * Build a token exactly the way iRIMS-V's App\Support\HelpdeskSsoToken does.
 *
 * @param  array<string, mixed>  $overrides
 */
function ssoToken(User $user, array $overrides = [], string $secret = 'shared-secret'): string
{
    $encode = fn (string $value): string => rtrim(strtr(base64_encode($value), '+/', '-_'), '=');

    $payload = $encode(json_encode([
        'v' => 1,
        'uid' => (string) $user->id,
        'exp' => time() + 60,
        'nonce' => Str::random(40),
        ...$overrides,
    ]));

    return $payload.'.'.$encode(hash_hmac('sha256', $payload, $secret, true));
}

test('a valid link from iRIMS-V signs the user in', function () {
    $user = User::factory()->create();

    $this->post(route('sso'), ['token' => ssoToken($user)])
        ->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($user);
});

test('a link works only once', function () {
    $user = User::factory()->create();
    $token = ssoToken($user);

    $this->post(route('sso'), ['token' => $token])->assertRedirect(route('dashboard'));

    auth()->logout();

    $this->post(route('sso'), ['token' => $token])
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors('username');
    $this->assertGuest();
});

test('an expired link is refused', function () {
    $user = User::factory()->create();

    $this->post(route('sso'), ['token' => ssoToken($user, ['exp' => time() - 1])])
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors('username');
    $this->assertGuest();
});

test('a link signed with another secret, or tampered with, is refused', function (string $case) {
    $user = User::factory()->create();

    $token = match ($case) {
        'wrong secret' => ssoToken($user, secret: 'other-secret'),
        // A valid signature lifted from one user's token onto another's payload.
        'swapped user id' => explode('.', ssoToken(User::factory()->create()))[0]
            .'.'.explode('.', ssoToken($user))[1],
        'garbage' => 'not-a-token',
    };

    $this->post(route('sso'), ['token' => $token])
        ->assertRedirect(route('login'));
    $this->assertGuest();
})->with(['wrong secret', 'swapped user id', 'garbage']);

test('an account that is not active in LRMIS is refused', function (string $state) {
    $user = User::factory()->{$state}()->create();

    $this->post(route('sso'), ['token' => ssoToken($user)])
        ->assertRedirect(route('login'));
    $this->assertGuest();
})->with(['pending', 'deactivated']);

test('with SSO turned off the link just opens the sign-in page', function () {
    config(['helpdesk.sso.secret' => null]);
    $user = User::factory()->create();

    $this->post(route('sso'), ['token' => ssoToken($user)])->assertRedirect(route('login'));
    $this->assertGuest();
});

test('a link for a different account replaces whoever was signed in', function () {
    $current = User::factory()->create();
    $incoming = User::factory()->create();

    $this->actingAs($current)
        ->post(route('sso'), ['token' => ssoToken($incoming)])
        ->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($incoming);
});

test('a token in the query string is ignored, so links never carry one', function () {
    $user = User::factory()->create();

    $this->post(route('sso').'?token='.urlencode(ssoToken($user)))
        ->assertRedirect(route('login'));
    $this->assertGuest();
});

test('the sign-in hand-off is exempt from CSRF, and nothing else is', function () {
    // Laravel always skips the CSRF check while running tests, so the
    // exemption itself is what can be pinned: iRIMS-V's cross-origin form post
    // cannot carry this app's CSRF token.
    expect(app(ValidateCsrfToken::class)->getExcludedPaths())->toBe(['sso']);
});

test('opening /sso directly just shows the sign-in page', function () {
    $this->get('/sso')->assertRedirect(route('login'));
});
