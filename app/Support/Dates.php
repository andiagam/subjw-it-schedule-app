<?php

namespace App\Support;

use Carbon\CarbonImmutable;

class Dates
{
    /**
     * Inclusive calendar-day count. Asia/Jakarta has no daylight-saving shift, so each day is 86400 seconds.
     */
    public static function inclusiveDays(string $start, string $end): int
    {
        $from = CarbonImmutable::parse($start)->startOfDay();
        $to = CarbonImmutable::parse($end)->startOfDay();

        return (int) (($to->getTimestamp() - $from->getTimestamp()) / 86400) + 1;
    }
}
