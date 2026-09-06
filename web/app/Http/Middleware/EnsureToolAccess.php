<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureToolAccess
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  $tool
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle(Request $request, Closure $next, string $tool): Response
    {
        if (!auth()->check()) {
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
            }
            return redirect()->route('login');
        }

        $user = auth()->user();

        // Admin has full access to all tools
        if ($user->role === 'admin') {
            return $next($request);
        }

        // For 2fa_generator: check system setting access mode ('all' by default)
        if ($tool === '2fa_generator') {
            $accessMode = \App\Models\BotSetting::where('key', 'tool_2fa_access_mode')->value('value') ?? 'all';
            if ($accessMode === 'all') {
                return $next($request);
            }
        }

        // Users (sellers or customers) have access if the tool is in allowed_tools list
        if (is_array($user->allowed_tools) && in_array($tool, $user->allowed_tools)) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json(['success' => false, 'message' => 'Akses ditolak.'], 403);
        }

        return redirect()->route('dashboard')->with('error', 'Akses ditolak. Anda tidak memiliki izin untuk mengakses tool ini.');
    }
}
