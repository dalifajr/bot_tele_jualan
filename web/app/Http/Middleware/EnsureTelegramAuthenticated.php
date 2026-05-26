<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureTelegramAuthenticated
{
    /**
     * Handle an incoming request.
     * Ensures the user is authenticated via Telegram login flow.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!Auth::check()) {
            return redirect()->route('login')->with('error', 'Silakan login terlebih dahulu.');
        }

        $user = Auth::user();

        if ($user->is_suspended && !$request->routeIs('suspended') && !$request->routeIs('logout')) {
            return redirect()->route('suspended');
        }

        return $next($request);
    }
}
