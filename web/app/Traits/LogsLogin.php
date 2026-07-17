<?php

namespace App\Traits;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Models\LoginLog;

trait LogsLogin
{
    protected function recordLoginLog(Request $request, $loginValue, $isSuccessful)
    {
        $ip = $request->ip();
        $userAgent = $request->header('User-Agent');
        
        $deviceType = 'Desktop';
        if (preg_match('/(Mobile|Android|iPhone|iPad)/i', $userAgent)) {
            $deviceType = 'Smartphone/Tablet';
        }

        $browser = 'Unknown';
        if (preg_match('/Telegram/i', $userAgent)) $browser = 'Telegram Browser';
        elseif (preg_match('/Edge/i', $userAgent)) $browser = 'Edge';
        elseif (preg_match('/Chrome/i', $userAgent)) $browser = 'Chrome';
        elseif (preg_match('/Firefox/i', $userAgent)) $browser = 'Firefox';
        elseif (preg_match('/Safari/i', $userAgent)) $browser = 'Safari';

        $location = 'Local / Unknown';
        if ($ip && $ip !== '127.0.0.1' && $ip !== '::1' && !str_starts_with($ip, '192.168.') && !str_starts_with($ip, '10.')) {
            try {
                $response = Http::timeout(2)->get("http://ip-api.com/json/{$ip}");
                if ($response->successful() && $response->json('status') === 'success') {
                    $location = ($response->json('city') ?? '') . ', ' . ($response->json('country') ?? 'Unknown');
                }
            } catch (\Exception $e) {
                // ignore
            }
        }

        LoginLog::create([
            'ip_address' => $ip,
            'username_or_email' => $loginValue,
            'is_successful' => $isSuccessful,
            'user_agent' => $userAgent,
            'device_type' => $deviceType,
            'browser' => $browser,
            'location' => trim($location, ', '),
        ]);
    }
}
