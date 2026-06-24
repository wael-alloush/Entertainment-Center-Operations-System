<?php

use App\Support\TimeFormatter;
use Carbon\Carbon;

it('formats afternoon times in twelve hour format', function (): void {
    $time = Carbon::parse('2026-06-09 14:30:00');

    expect(TimeFormatter::formatTime($time))->toBe('02:30 م');
});

it('formats morning times in twelve hour format', function (): void {
    $time = Carbon::parse('2026-06-09 08:05:00');

    expect(TimeFormatter::formatTime($time))->toBe('08:05 ص');
});

it('formats noon as twelve pm', function (): void {
    $time = Carbon::parse('2026-06-09 12:00:00');

    expect(TimeFormatter::formatTime($time))->toBe('12:00 م');
});

it('formats midnight as twelve am', function (): void {
    $time = Carbon::parse('2026-06-09 00:15:00');

    expect(TimeFormatter::formatTime($time))->toBe('12:15 ص');
});

it('formats date and time together', function (): void {
    $time = Carbon::parse('2026-06-09 16:45:00');

    expect(TimeFormatter::formatDateTime($time))->toBe('2026-06-09 04:45 م');
});

it('returns default when time is missing', function (): void {
    expect(TimeFormatter::formatTimeOrDefault(null))->toBe('—');
});

it('formats dates for visit history', function (): void {
    $time = Carbon::parse('2026-06-09 16:45:00');

    expect(TimeFormatter::formatDate($time))->toBe('2026-06-09');
});

it('formats arabic weekday names', function (): void {
    expect(TimeFormatter::formatArabicWeekday(Carbon::parse('2026-06-12')))->toBe('الجمعة');
    expect(TimeFormatter::formatArabicWeekday(Carbon::parse('2026-06-08')))->toBe('الاثنين');
});
