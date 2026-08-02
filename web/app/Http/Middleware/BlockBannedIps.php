<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Auth;

class BlockBannedIps
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Admin users are never blocked from accessing the panel or executing unblock actions
        if (Auth::check() && Auth::user()->role === 'admin') {
            return $next($request);
        }

        $ip = $request->ip();
        $deviceFp = $request->input('device_fingerprint') ?: ($request->cookie('_sec_device_fp') ?: $request->header('X-Device-Fingerprint'));
        $deviceId = $request->input('device_id') ?: ($request->cookie('_sec_device_id') ?: $request->header('X-Device-ID'));
        $userAgent = $request->header('User-Agent');

        $isBlocked = false;
        $reason = 'Kamu diblokir';

        // 1. Check IP block
        if ($ip && Cache::has('blocked_ip:' . $ip)) {
            $isBlocked = true;
        }

        // 2. Check Device Fingerprint block
        if (!$isBlocked && $deviceFp && Cache::has('blocked_device_fp:' . $deviceFp)) {
            $isBlocked = true;
        }

        // 3. Check Fallback User-Agent Fingerprint block
        if (!$isBlocked && $userAgent) {
            $fallbackFp = 'fp_ua_' . substr(md5($userAgent), 0, 16);
            if (Cache::has('blocked_device_fp:' . $fallbackFp)) {
                $isBlocked = true;
            }
        }

        // 4. Check Persistent Device Cookie ID block
        if (!$isBlocked && $deviceId && Cache::has('blocked_device_id:' . $deviceId)) {
            $isBlocked = true;
        }

        // 5. Check User Account Suspension if logged in
        if (!$isBlocked && Auth::check()) {
            $user = Auth::user();
            if ($user && $user->is_suspended) {
                $isBlocked = true;
            }
        }

        if ($isBlocked) {
            if (Auth::check()) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $reason
                ], 403);
            }

            abort(403, $reason);
        }

        return $next($request);
    }
}
