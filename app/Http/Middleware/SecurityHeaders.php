<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Security headers on every response, including a Content-Security-Policy.
 *
 * Inline <script> tags only run with this request's nonce: write them as
 * <script nonce="{{ $cspNonce }}">. Inline event handlers (onclick="…") don't run at all.
 * Inline style attributes are allowed (style="--district: …", and Leaflet sets styles).
 */
class SecurityHeaders
{
    /** Hosts the pages talk to, besides the site itself. */
    private const MAP_TILES = 'https://geo.api.vlaanderen.be';

    private const ADDRESS_SEARCH = 'https://nominatim.openstreetmap.org';

    /**
     * Browsers check every redirect of a form submission against form-action, and only report
     * the first URL. The pay form goes /betalen → Mollie → possibly a bank or PayPal, so the
     * page with that form allows any https target; every other page only posts to the site.
     */
    private const PAY_PAGE_FORM_TARGETS = 'https:';

    public function handle(Request $request, Closure $next): Response
    {
        $nonce = base64_encode(random_bytes(16));
        View::share('cspNonce', $nonce);

        $response = $next($request);

        $headers = $response->headers;
        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $headers->set('X-Frame-Options', 'DENY');
        $headers->set('Cross-Origin-Opener-Policy', 'same-origin');
        $headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(), usb=()');

        if ($request->isSecure()) {
            $headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        // Laravel's own error page (only with APP_DEBUG) uses inline scripts without a nonce.
        if (! (config('app.debug') && property_exists($response, 'exception') && $response->exception)) {
            $headers->set('Content-Security-Policy', $this->policy($nonce, $request->isSecure(), $request->routeIs('inschrijvingen.show')));
        }

        return $response;
    }

    private function policy(string $nonce, bool $secure, bool $payPage): string
    {
        $formAction = "form-action 'self'".($payPage ? ' '.self::PAY_PAGE_FORM_TARGETS : '');

        $directives = [
            "default-src 'self'",
            "script-src 'self' 'nonce-{$nonce}'",
            "style-src 'self' 'unsafe-inline'",
            "img-src 'self' data: blob: ".self::MAP_TILES,
            "connect-src 'self' ".self::ADDRESS_SEARCH,
            "font-src 'self'",
            $formAction,
            "frame-ancestors 'none'",
            "base-uri 'self'",
            "object-src 'none'",
        ];

        if ($secure) {
            $directives[] = 'upgrade-insecure-requests';
        }

        return implode('; ', $directives);
    }
}
