<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

class AddSecurityHeaders
{
    /**
     * Add browser security headers to the response.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $mode = (string) config('helpdesk.csp.mode');

        if ($mode !== 'off') {
            // Every script and style tag Vite renders carries this nonce, and so does the theme script in the root view.
            Vite::useCspNonce();
        }

        $response = $next($request);

        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        if ($mode === 'enforce' || $mode === 'report') {
            $response->headers->set(
                $mode === 'enforce' ? 'Content-Security-Policy' : 'Content-Security-Policy-Report-Only',
                $this->contentSecurityPolicy(),
            );
        }

        return $response;
    }

    /**
     * Build the policy: this app's own scripts, styles, images and fonts, plus the LRMIS photo host,
     * the Vite dev server while it runs, and the Reverb WebSocket.
     */
    private function contentSecurityPolicy(): string
    {
        $devServer = $this->viteDevServer();
        $nonce = Vite::cspNonce();

        $scriptSources = array_filter(["'self'", "'nonce-{$nonce}'", $devServer]);
        // Vue binds inline style attributes (for example the width of a data bar), which need 'unsafe-inline'.
        $styleSources = array_filter(["'self'", "'unsafe-inline'", $devServer]);
        $imageSources = array_filter(["'self'", 'data:', 'blob:', $this->origin((string) config('helpdesk.csp.photo_url')), $devServer]);
        $fontSources = array_filter(["'self'", 'data:', $devServer]);
        $mediaSources = array_filter(["'self'", 'blob:', $devServer]);
        $connectSources = array_filter(["'self'", $devServer, $devServer === null ? null : str_replace('http', 'ws', $devServer), $this->reverbSocket()]);

        return implode('; ', [
            "default-src 'self'",
            "base-uri 'self'",
            "frame-ancestors 'none'",
            "form-action 'self'",
            "object-src 'none'",
            'script-src '.implode(' ', $scriptSources),
            'style-src '.implode(' ', $styleSources),
            'img-src '.implode(' ', $imageSources),
            'font-src '.implode(' ', $fontSources),
            'media-src '.implode(' ', $mediaSources),
            'connect-src '.implode(' ', $connectSources),
        ]);
    }

    /**
     * The origin of the Vite dev server while `npm run dev` is serving the assets, read from its hot file.
     */
    private function viteDevServer(): ?string
    {
        if (! Vite::isRunningHot()) {
            return null;
        }

        return $this->origin(rtrim((string) file_get_contents(Vite::hotFile())));
    }

    /**
     * The WebSocket origin the browser connects to for live updates, when Reverb is configured.
     */
    private function reverbSocket(): ?string
    {
        $host = (string) config('helpdesk.csp.reverb_host');

        if ($host === '') {
            return null;
        }

        $scheme = config('helpdesk.csp.reverb_scheme') === 'https' ? 'wss' : 'ws';
        $port = (string) config('helpdesk.csp.reverb_port');

        return "{$scheme}://{$host}".($port === '' ? '' : ":{$port}");
    }

    /**
     * Reduce a URL to its origin ("https://host:port"), or null when there is no host in it.
     */
    private function origin(string $url): ?string
    {
        $parts = parse_url($url);

        if (! is_array($parts) || ! isset($parts['host'])) {
            return null;
        }

        return ($parts['scheme'] ?? 'https').'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '');
    }
}
