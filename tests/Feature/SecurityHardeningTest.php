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

test('responses carry a content security policy with a nonce for the page scripts', function () {
    config()->set('helpdesk.csp.mode', 'enforce');
    config()->set('helpdesk.csp.photo_url', 'https://lrmis.test/some/path');
    config()->set('helpdesk.csp.reverb_host', 'ws.lrmis.test');
    config()->set('helpdesk.csp.reverb_port', '443');
    config()->set('helpdesk.csp.reverb_scheme', 'https');

    $response = $this->get(route('login'))->assertOk();
    $policy = $response->headers->get('Content-Security-Policy');

    expect($policy)->toContain("default-src 'self'")
        ->toContain("frame-ancestors 'none'")
        ->toMatch("/script-src 'self' 'nonce-[A-Za-z0-9]+'/")
        ->toContain("img-src 'self' data: blob: https://lrmis.test")
        ->toContain('connect-src \'self\' wss://ws.lrmis.test:443');

    preg_match("/'nonce-([A-Za-z0-9]+)'/", $policy, $matches);
    expect($response->getContent())->toContain('nonce="'.$matches[1].'"');
});

test('the content security policy can be switched to report-only or off', function (string $mode, ?string $header) {
    config()->set('helpdesk.csp.mode', $mode);

    $response = $this->get(route('login'))->assertOk();

    if ($header === null) {
        $response->assertHeaderMissing('Content-Security-Policy')->assertHeaderMissing('Content-Security-Policy-Report-Only');
    } else {
        expect($response->headers->has($header))->toBeTrue();
    }
})->with([
    'report' => ['report', 'Content-Security-Policy-Report-Only'],
    'off' => ['off', null],
]);

test('strict transport security is only sent over https', function () {
    $this->get('https://localhost/')
        ->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
});

test('the https scheme forwarded by a local proxy is trusted', function () {
    $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
        ->withHeader('X-Forwarded-Proto', 'https')
        ->get(route('login'))
        ->assertOk()
        ->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
});

test('forwarded headers from the public internet are ignored', function () {
    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.5'])
        ->withHeader('X-Forwarded-Proto', 'https')
        ->get(route('login'))
        ->assertOk()
        ->assertHeaderMissing('Strict-Transport-Security');
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
