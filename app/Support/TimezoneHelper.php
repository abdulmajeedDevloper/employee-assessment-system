<?php

namespace App\Support;

use DateTime;
use DateTimeZone;
use Exception;

class TimezoneHelper
{
    /**
     * Format a UTC database timestamp into target timezone.
     *
     * @param string|null $utcTimestamp
     * @param string|null $targetTimezone
     * @param string $format
     * @return string
     */
    public static function format(?string $utcTimestamp, ?string $targetTimezone = null, string $format = 'Y-m-d H:i:s'): string
    {
        if (empty($utcTimestamp)) {
            return '-';
        }

        $tz = $targetTimezone ?? env('APP_TIMEZONE', 'Asia/Riyadh');

        try {
            $dt = new DateTime($utcTimestamp, new DateTimeZone('UTC'));
            $dt->setTimezone(new DateTimeZone($tz));
            return $dt->format($format);
        } catch (Exception $e) {
            return $utcTimestamp;
        }
    }
}
