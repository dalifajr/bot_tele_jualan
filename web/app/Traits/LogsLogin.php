<?php

namespace App\Traits;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Auth;
use App\Models\LoginLog;
use App\Models\User;

trait LogsLogin
{
    protected function recordLoginLog(Request $request, $loginValue, $isSuccessful, $forcedBrowser = null, $userId = null)
    {
        $ip = $request->ip();
        $userAgent = $request->header('User-Agent');
        
        $deviceType = 'Desktop';
        if (preg_match('/(Mobile|Android|iPhone|iPad)/i', $userAgent)) {
            $deviceType = 'Smartphone/Tablet';
        }

        $browser = 'Unknown';
        if ($forcedBrowser) {
            $browser = $forcedBrowser;
        } elseif (preg_match('/Telegram/i', $userAgent)) {
            $browser = 'Telegram Browser';
        } elseif (preg_match('/Edge/i', $userAgent)) {
            $browser = 'Edge';
        } elseif (preg_match('/Chrome/i', $userAgent)) {
            $browser = 'Chrome';
        } elseif (preg_match('/Firefox/i', $userAgent)) {
            $browser = 'Firefox';
        } elseif (preg_match('/Safari/i', $userAgent)) {
            $browser = 'Safari';
        }

        $geo = \App\Services\GeoIpService::resolve($ip, $request->header('CF-IPCountry'));
        $location = $geo['location'] ?? 'Local / Unknown';

        if (!$userId) {
            if (Auth::check()) {
                $userId = Auth::id();
            } elseif ($loginValue) {
                $userId = User::where('username', $loginValue)->orWhere('email', $loginValue)->value('id');
            }
        }

        $deviceFingerprint = $request->input('device_fingerprint') ?: ($request->cookie('_sec_device_fp') ?: $request->header('X-Device-Fingerprint'));
        $deviceId = $request->input('device_id') ?: ($request->cookie('_sec_device_id') ?: $request->header('X-Device-ID'));

        LoginLog::create([
            'user_id' => $userId,
            'ip_address' => $ip,
            'username_or_email' => $loginValue,
            'is_successful' => $isSuccessful,
            'user_agent' => $userAgent,
            'device_type' => $deviceType,
            'browser' => $browser,
            'device_fingerprint' => $deviceFingerprint,
            'device_id' => $deviceId,
            'location' => trim($location, ', '),
            'country' => $geo['country'] ?? null,
            'country_code' => $geo['country_code'] ?? null,
            'region' => $geo['region'] ?? null,
            'city' => $geo['city'] ?? null,
            'latitude' => $geo['latitude'] ?? null,
            'longitude' => $geo['longitude'] ?? null,
        ]);
    }
}
