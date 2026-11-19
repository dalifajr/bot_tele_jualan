<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserSession extends Model
{
    protected $table = 'user_sessions';

    protected $fillable = [
        'user_id',
        'session_id',
        'ip_address',
        'user_agent',
        'device_label',
        'location_label',
        'last_active_at',
        'revoked_at',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'last_active_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function isCurrent(?string $currentSessionId = null): bool
    {
        $sid = $currentSessionId ?? session()->getId();
        return $sid !== '' && hash_equals((string) $this->session_id, (string) $sid);
    }

    public static function parseDeviceLabel(?string $userAgent): string
    {
        $ua = (string) $userAgent;
        if ($ua === '') {
            return 'Perangkat Tidak Dikenal';
        }

        $browser = 'Browser';
        if (stripos($ua, 'Edg/') !== false) {
            $browser = 'Microsoft Edge';
        } elseif (stripos($ua, 'OPR/') !== false || stripos($ua, 'Opera') !== false) {
            $browser = 'Opera';
        } elseif (stripos($ua, 'Chrome/') !== false && stripos($ua, 'Chromium') === false) {
            $browser = 'Google Chrome';
        } elseif (stripos($ua, 'Firefox/') !== false) {
            $browser = 'Mozilla Firefox';
        } elseif (stripos($ua, 'Safari/') !== false && stripos($ua, 'Chrome/') === false) {
            $browser = 'Safari';
        } elseif (stripos($ua, 'Telegram') !== false) {
            $browser = 'Telegram WebApp';
        }

        $os = 'Unknown OS';
        if (stripos($ua, 'Windows NT 10') !== false || stripos($ua, 'Windows NT 11') !== false) {
            $os = 'Windows';
        } elseif (stripos($ua, 'Windows') !== false) {
            $os = 'Windows';
        } elseif (stripos($ua, 'Android') !== false) {
            $os = 'Android';
        } elseif (stripos($ua, 'iPhone') !== false || stripos($ua, 'iPad') !== false) {
            $os = 'iOS';
        } elseif (stripos($ua, 'Mac OS X') !== false || stripos($ua, 'Macintosh') !== false) {
            $os = 'macOS';
        } elseif (stripos($ua, 'Linux') !== false) {
            $os = 'Linux';
        }

        return "{$browser} · {$os}";
    }
}
