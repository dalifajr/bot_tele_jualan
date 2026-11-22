<?php

namespace App\Support;

use Carbon\Carbon;
use DateTimeInterface;
use DateTimeZone;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SystemTimezone
{
    public const CACHE_KEY = 'system_timezone_active';
    public const DEFAULT_TIMEZONE = 'Asia/Jakarta';

    /**
     * Resolve the active system timezone configured in bot_settings (cached).
     */
    public static function active(): string
    {
        $tz = Cache::remember(self::CACHE_KEY, 60, function () {
            try {
                if (Schema::hasTable('bot_settings')) {
                    $configured = DB::table('bot_settings')
                        ->where('key', 'system_timezone')
                        ->value('value');

                    if (is_string($configured) && trim($configured) !== '') {
                        return trim($configured);
                    }
                }
            } catch (\Throwable $e) {
                // Fallback safely during migrations / bootstrap
            }

            return self::DEFAULT_TIMEZONE;
        });

        if (!is_string($tz) || !in_array($tz, DateTimeZone::listIdentifiers(), true)) {
            return self::DEFAULT_TIMEZONE;
        }

        return $tz;
    }

    /**
     * Clear cached active timezone immediately when Admin updates settings.
     */
    public static function forgetCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Current time in the active system timezone.
     */
    public static function now(): Carbon
    {
        return Carbon::now(self::active());
    }

    /**
     * Convert a Carbon/DateTime or raw UTC database datetime string into the active system timezone.
     */
    public static function toSystem($value): ?Carbon
    {
        if (empty($value)) {
            return null;
        }

        $tz = self::active();

        if ($value instanceof DateTimeInterface) {
            return Carbon::instance($value)->copy()->timezone($tz);
        }

        if (is_numeric($value)) {
            return Carbon::createFromTimestamp((int) $value, $tz);
        }

        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }

        // If string already includes explicit timezone/offset, respect it then convert
        if (preg_match('/(Z|[+\-]\d{2}:?\d{2}|GMT|UTC)$/i', $raw)) {
            return Carbon::parse($raw)->timezone($tz);
        }

        // Raw DB strings are stored in UTC (config('app.timezone'))
        return Carbon::parse($raw, config('app.timezone', 'UTC'))->timezone($tz);
    }

    /**
     * Short human-readable timezone abbreviation (WIB / WITA / WIT / SGT / UTC).
     */
    public static function abbr(?string $timezone = null): string
    {
        $tz = $timezone ?: self::active();

        return match ($tz) {
            'Asia/Jakarta', 'Asia/Pontianak' => 'WIB',
            'Asia/Makassar', 'Asia/Ujung_Pandang' => 'WITA',
            'Asia/Jayapura' => 'WIT',
            'Asia/Singapore' => 'SGT',
            'UTC', 'Etc/UTC', 'GMT' => 'UTC',
            default => Carbon::now($tz)->format('T'),
        };
    }
}
