<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class UpdateUserLastSeen
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check()) {
            $user = Auth::user();
            // Throttle database update to once per minute per user (timezone-safe instant comparison)
            if (!$user->last_seen_at || $user->last_seen_at->lessThanOrEqualTo(now()->subMinute())) {
                $user->timestamps = false;
                $user->update(['last_seen_at' => now()]);
            }
        }

        return $next($request);
    }
}
