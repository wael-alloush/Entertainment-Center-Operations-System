<?php

namespace App\Support;

use Carbon\CarbonInterface;

class TimeFormatter
{
    public static function formatTime(?CarbonInterface $dateTime): ?string
    {
        if ($dateTime === null) {
            return null;
        }

        $hour = $dateTime->format('h');
        $minute = $dateTime->format('i');
        $period = $dateTime->format('A') === 'AM' ? 'ص' : 'م';

        return "{$hour}:{$minute} {$period}";
    }

    public static function formatDateTime(?CarbonInterface $dateTime): ?string
    {
        if ($dateTime === null) {
            return null;
        }

        return $dateTime->format('Y-m-d').' '.self::formatTime($dateTime);
    }

    public static function formatTimeOrDefault(?CarbonInterface $dateTime, string $default = '—'): string
    {
        return self::formatTime($dateTime) ?? $default;
    }

    public static function formatDate(?CarbonInterface $dateTime): ?string
    {
        return $dateTime?->format('Y-m-d');
    }

    public static function formatArabicWeekday(?CarbonInterface $dateTime): ?string
    {
        if ($dateTime === null) {
            return null;
        }

        return $dateTime->locale('ar')->translatedFormat('l');
    }

    public static function formatLateMinutes(?int $minutes): ?string
    {
        if ($minutes === null) {
            return null;
        }

        if ($minutes > 0) {
            return 'متأخر '.self::formatHoursAndMinutes($minutes);
        }

        if ($minutes < 0) {
            return 'حضر قبل '.self::formatHoursAndMinutes(abs($minutes));
        }

        return 'في الوقت';
    }

    private static function formatHoursAndMinutes(int $minutes): string
    {
        $hours = intdiv($minutes, 60);
        $mins = $minutes % 60;

        if ($hours === 0) {
            return "{$mins} د";
        }

        if ($mins === 0) {
            return "{$hours} س";
        }

        return "{$hours} س {$mins} د";
    }
}
