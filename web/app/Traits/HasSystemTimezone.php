<?php

namespace App\Traits;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

trait HasSystemTimezone
{
    /**
     * Convert database datetime values to the system timezone when retrieved.
     */
    protected function asDateTime($value)
    {
        $dateTime = parent::asDateTime($value);

        if ($dateTime instanceof Carbon) {
            $timezone = Cache::remember('system_timezone_active', 60, function () {
                try {
                    if (Schema::hasTable('bot_settings')) {
                        return DB::table('bot_settings')->where('key', 'system_timezone')->value('value') ?: 'Asia/Jakarta';
                    }
                } catch (\Exception $e) {
                    // Fallback
                }
                return 'Asia/Jakarta';
            });

            // Convert to system timezone
            return $dateTime->timezone($timezone);
        }

        return $dateTime;
    }

    /**
     * Convert datetime values back to UTC when saving to the database.
     */
    public function fromDateTime($value)
    {
        if (empty($value)) {
            return $value;
        }

        $dateTime = parent::asDateTime($value);
        if ($dateTime instanceof Carbon) {
            return $dateTime->copy()->timezone('UTC')->format($this->getDateFormat());
        }

        return parent::fromDateTime($value);
    }
}
