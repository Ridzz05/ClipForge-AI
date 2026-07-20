<?php

declare(strict_types=1);

namespace App\Utils;

class TimeFormatter
{
    /**
     * Format milliseconds into MM:SS timestamp string.
     */
    public static function msToTimestamp(int $ms): string
    {
        $seconds = (int) floor($ms / 1000);
        $minutes = (int) floor($seconds / 60);
        $remainingSeconds = $seconds % 60;

        return sprintf('%02d:%02d', $minutes, $remainingSeconds);
    }

    /**
     * Format seconds into human readable duration string.
     */
    public static function formatDuration(int $seconds): string
    {
        if ($seconds < 60) {
            return "{$seconds}s";
        }

        $minutes = (int) floor($seconds / 60);
        $remainingSeconds = $seconds % 60;

        return sprintf('%02d:%02d', $minutes, $remainingSeconds);
    }
}
