<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

/**
 * Signs in a user arriving from iRIMS-V's "Support Center" link, which POSTs the token.
 *
 * iRIMS-V and the helpdesk share the users table, so iRIMS-V only has to vouch
 * for which account is signed in. It does that with a token of the form
 *   base64url(json payload) "." base64url(HMAC-SHA256(payload part, secret))
 * with payload {"v":1,"uid":"<users.id>","exp":<unix ts>,"nonce":"<random>"},
 * issued by App\Support\HelpdeskSsoToken in iRIMS-V — keep the two in step.
 *
 * A token is accepted once, before `exp`, when the signature matches and the
 * account is still active. Anything else lands on the normal sign-in page.
 */
class SingleSignOnController extends Controller
{
    private const string FAILED = 'Your sign-in link from iRIMS-V has expired or is invalid. Open the Support Center from iRIMS-V again, or sign in below.';

    public function __invoke(Request $request): RedirectResponse
    {
        $secret = (string) config('helpdesk.sso.secret');
        // The form body only, never the query string: a token in a URL ends up
        // in browser history and access logs, which is why iRIMS-V POSTs it.
        $token = (string) $request->request->get('token', '');

        if ($secret === '' || $token === '') {
            return redirect()->route('login');
        }

        $payload = $this->verifiedPayload($token, $secret);

        // Burn the nonce before anything else can use it. Cache::add() is
        // atomic, so two requests racing with one token cannot both get in.
        // It is kept a little past `exp`, after which the expiry check alone
        // already refuses the token.
        if ($payload === null
            || ! Cache::add('helpdesk-sso-nonce:'.$payload['nonce'], true, max(1, $payload['exp'] - time() + 60))) {
            return $this->fail($request);
        }

        $user = User::find($payload['uid']);

        if (! $user || ! $user->isActive()) {
            return $this->fail($request);
        }

        // Someone else may be signed in on this browser; the link speaks for
        // the iRIMS-V account, so that session ends rather than being reused.
        if (Auth::check() && ! Auth::user()->is($user)) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
        }

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('dashboard');
    }

    /**
     * The token's payload when the signature, shape and expiry all check out.
     *
     * @return array{v: int, uid: string, exp: int, nonce: string}|null
     */
    private function verifiedPayload(string $token, string $secret): ?array
    {
        $parts = explode('.', $token);

        if (count($parts) !== 2) {
            return null;
        }

        [$encodedPayload, $encodedSignature] = $parts;

        $expected = hash_hmac('sha256', $encodedPayload, $secret, true);
        $signature = $this->base64UrlDecode($encodedSignature);

        if ($signature === null || ! hash_equals($expected, $signature)) {
            return null;
        }

        $payload = json_decode((string) $this->base64UrlDecode($encodedPayload), true);

        // A token that would stay valid for longer than iRIMS-V ever issues one is refused too,
        // whatever signed it: a leaked secret must not mint long-lived sign-in links.
        if (! is_array($payload)
            || ($payload['v'] ?? null) !== 1
            || ! is_string($payload['uid'] ?? null)
            || ! is_int($payload['exp'] ?? null)
            || ! is_string($payload['nonce'] ?? null)
            || strlen($payload['nonce']) < 32
            || $payload['exp'] < time()
            || $payload['exp'] > time() + (int) config('helpdesk.sso.max_lifetime')) {
            return null;
        }

        return $payload;
    }

    private function base64UrlDecode(string $value): ?string
    {
        $decoded = base64_decode(strtr($value, '-_', '+/'), true);

        return $decoded === false ? null : $decoded;
    }

    private function fail(Request $request): RedirectResponse
    {
        // A refused link changes nothing: whoever was already signed in on this
        // browser simply carries on.
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }

        return redirect()->route('login')->withErrors(['username' => self::FAILED]);
    }
}
