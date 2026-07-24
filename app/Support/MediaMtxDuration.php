<?php

namespace App\Support;

/**
 * Parse MediaMTX / Go-style duration strings (e.g. 1h2m3.5s) into seconds.
 */
class MediaMtxDuration
{
    public static function toSeconds(?string $raw): ?float
    {
        if ($raw === null) {
            return null;
        }

        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }

        if (is_numeric($raw)) {
            return max(0.0, (float) $raw);
        }

        if (! preg_match(
            '/^(?:(?P<h>\d+(?:\.\d+)?)h)?(?:(?P<m>\d+(?:\.\d+)?)m)?(?:(?P<s>\d+(?:\.\d+)?)s)?$/i',
            $raw,
            $matches,
        )) {
            return null;
        }

        $hours = isset($matches['h']) && $matches['h'] !== '' ? (float) $matches['h'] : 0.0;
        $minutes = isset($matches['m']) && $matches['m'] !== '' ? (float) $matches['m'] : 0.0;
        $seconds = isset($matches['s']) && $matches['s'] !== '' ? (float) $matches['s'] : 0.0;

        if ($hours === 0.0 && $minutes === 0.0 && $seconds === 0.0 && ! preg_match('/\d/', $raw)) {
            return null;
        }

        return max(0.0, ($hours * 3600) + ($minutes * 60) + $seconds);
    }
}
