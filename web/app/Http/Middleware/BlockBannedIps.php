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
        $ip = $request->ip();
        $deviceFp = $request->input('device_fingerprint') ?: ($request->cookie('_sec_device_fp') ?: $request->header('X-Device-Fingerprint'));
        $deviceId = $request->input('device_id') ?: ($request->cookie('_sec_device_id') ?: $request->header('X-Device-ID'));

        if ($ip && Cache::has('blocked_ip:' . $ip)) {
            abort(403, 'Akses ditolak. IP Anda telah diblokir karena aktivitas mencurigakan.');
        }

        if ($deviceFp && Cache::has('blocked_device_fp:' . $deviceFp)) {
            abort(403, 'Akses ditolak. Perangkat Anda (Device Fingerprint) telah diblokir karena aktivitas mencurigakan.');
        }

        if (!$deviceFp && $request->header('User-Agent')) {
            $fallbackFp = 'fp_ua_' . substr(md5($request->header('User-Agent')), 0, 16);
            if (Cache::has('blocked_device_fp:' . $fallbackFp)) {
                abort(403, 'Akses ditolak. Perangkat Anda (User Agent/Device) telah diblokir karena aktivitas mencurigakan.');
            }
        }

        if ($deviceId && Cache::has('blocked_device_id:' . $deviceId)) {
            abort(403, 'Akses ditolak. Perangkat ini (Device ID) telah diblokir karena aktivitas mencurigakan.');
        }

        return $next($request);
    }
}
