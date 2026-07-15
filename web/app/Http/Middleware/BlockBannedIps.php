<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Cache;

class BlockBannedIps
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (Cache::has('blocked_ip:' . $request->ip())) {
            abort(403, 'Akses ditolak. Anda telah diblokir karena aktivitas mencurigakan.');
        }

        return $next($request);
    }
}
