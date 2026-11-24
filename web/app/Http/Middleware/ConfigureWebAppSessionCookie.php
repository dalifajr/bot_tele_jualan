<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ConfigureWebAppSessionCookie
{
    /**
     * Allow Telegram Web / Desktop Mini App cross-site iframes over HTTPS to persist
     * Laravel session cookies using SameSite=None; Secure; Partitioned (CHIPS),
     * while keeping SameSite=Lax for regular top-level browser traffic and HTTP local dev.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $isHttps = $request->isSecure()
            || strtolower((string) $request->headers->get('X-Forwarded-Proto', '')) === 'https';

        if ($isHttps && $this->isTelegramIframeContext($request)) {
            config([
                'session.secure' => true,
                'session.same_site' => 'none',
                'session.partitioned' => true,
            ]);
        }

        return $next($request);
    }

    private function isTelegramIframeContext(Request $request): bool
    {
        if ($request->is('auth/telegram/webapp')) {
            return true;
        }

        $fetchDest = strtolower((string) $request->headers->get('Sec-Fetch-Dest', ''));
        if ($fetchDest === 'iframe') {
            return true;
        }

        $referer = strtolower((string) $request->headers->get('Referer', ''));
        if ($referer !== '' && str_contains($referer, 'telegram.org')) {
            return true;
        }

        $ua = (string) $request->headers->get('User-Agent', '');
        if ($ua !== '' && preg_match('/Telegram/i', $ua)) {
            return true;
        }

        return false;
    }
}
