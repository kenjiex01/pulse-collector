<?php

namespace App\Support;

use Carbon\Carbon;
use Carbon\CarbonInterface;

final class CollectorDisplayTime
{
    /**
     * Format app/DB timestamps for the collector UI (exports, sync, collected-at).
     * Legacy rows were stored while APP timezone was UTC — treat naive DB values as UTC when configured.
     */
    public static function format(?CarbonInterface $value, string $pattern = 'Y-m-d h:i A'): string
    {
        if ($value === null) {
            return '—';
        }

        $displayTimezone = (string) config('app.timezone', 'Asia/Manila');

        if (config('biometric.timestamps_stored_as_utc', true)) {
            $instant = Carbon::parse($value->format('Y-m-d H:i:s'), 'UTC');

            return $instant->timezone($displayTimezone)->format($pattern);
        }

        return $value->copy()->timezone($displayTimezone)->format($pattern);
    }

    public static function timezoneLabel(): string
    {
        $tz = (string) config('app.timezone', 'Asia/Manila');

        return match ($tz) {
            'Asia/Manila' => 'Philippine Time (PHT)',
            default => str_replace('_', ' ', $tz),
        };
    }
}
