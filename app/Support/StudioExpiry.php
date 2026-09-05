<?php

namespace App\Support;

use Carbon\CarbonInterface;

class StudioExpiry
{
    /**
     * One year in minutes. Studio stays open for long broadcasts, so the
     * session cookie and signed action URLs must outlast a service.
     */
    public const LIFETIME_MINUTES = 525_600;

    public static function minutes(): int
    {
        return max(1, (int) config('session.lifetime', self::LIFETIME_MINUTES));
    }

    public static function at(): CarbonInterface
    {
        return now()->addMinutes(self::minutes());
    }
}
