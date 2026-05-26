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

        // Check if the user is suspended (opsional, jika ada)
        // if ($user->is_suspended) { ... }

        return $next($request);
    }
}
