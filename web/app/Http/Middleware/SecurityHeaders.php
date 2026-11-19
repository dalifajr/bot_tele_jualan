<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    /**
     * Attach defensive HTTP security headers and per-request CSP nonce
     * without breaking Telegram WebApp framing or payment/map integrations.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $nonce = Vite::useCspNonce();
        View::share('cspNonce', $nonce);

        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff', false);
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin', false);
        $response->headers->set('Permissions-Policy', 'geolocation=(), microphone=(), camera=()', false);
        $response->headers->set('X-CSP-Nonce-Active', '1', false);

        $cspDirectives = [
            "object-src 'none'",
            "base-uri 'self'",
            "frame-ancestors 'self' https://web.telegram.org https://*.telegram.org",
        ];

        $response->headers->set(
            'Content-Security-Policy',
            implode('; ', $cspDirectives),
            false
        );

        return $response;
    }
}
