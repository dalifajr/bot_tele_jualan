<?php

namespace App\Traits;

use App\Support\SystemTimezone;
use Carbon\Carbon;
use DateTimeInterface;

trait HasSystemTimezone
{
    /**
     * Convert database UTC datetime values to the active system timezone when retrieved.
     */
    protected function asDateTime($value)
    {
        $dateTime = parent::asDateTime($value);

        if ($dateTime instanceof Carbon) {
            return $dateTime->timezone(SystemTimezone::active());
        }

        return $dateTime;
    }

    /**
     * Normalize datetime values to UTC before persisting to the database.
     * - DateTimeInterface / Carbon instances keep their real instant and convert cleanly to UTC.
     * - Naive strings (e.g. HTML datetime-local inputs without offset) are interpreted in the
     *   active system timezone first, then converted to UTC so form saves never shift by 7 hours.
     */
    public function fromDateTime($value)
    {
        if (empty($value)) {
            return $value;
        }

        if ($value instanceof DateTimeInterface) {
            return Carbon::instance($value)->copy()->timezone('UTC')->format($this->getDateFormat());
        }

        if (is_numeric($value)) {
            return Carbon::createFromTimestamp((int) $value, 'UTC')->format($this->getDateFormat());
        }

        if (is_string($value)) {
            $trimmed = trim($value);
            if ($trimmed === '') {
                return null;
            }

            if (preg_match('/(Z|[+\-]\d{2}:?\d{2}|GMT|UTC)$/i', $trimmed)) {
                return Carbon::parse($trimmed)->timezone('UTC')->format($this->getDateFormat());
            }

            return Carbon::parse($trimmed, SystemTimezone::active())
                ->timezone('UTC')
                ->format($this->getDateFormat());
        }

        $dateTime = parent::asDateTime($value);
        if ($dateTime instanceof Carbon) {
            return $dateTime->copy()->timezone('UTC')->format($this->getDateFormat());
        }

        return parent::fromDateTime($value);
    }
}
