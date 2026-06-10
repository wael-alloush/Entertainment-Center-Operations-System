<?php

declare(strict_types=1);

use App\Models\AttendanceRecord;
use App\Models\Employee;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$employee = Employee::query()->active()->orderBy('name')->first();

if ($employee === null) {
    fwrite(STDERR, "No active employee found.\n");
    exit(1);
}

$start = now()->subDays(29);

for ($i = 0; $i < 30; $i++) {
    $date = $start->copy()->addDays($i)->toDateString();
    $lateOptions = [0, 0, 5, 12, -10, 30, 0, 3];
    $late = $lateOptions[$i % count($lateOptions)];
    $checkInMinutes = 22 + $late;
    $checkInHour = 8;

    if ($checkInMinutes < 0) {
        $checkInHour = 7;
        $checkInMinutes = 60 + $checkInMinutes;
    } elseif ($checkInMinutes >= 60) {
        $checkInHour = 9;
        $checkInMinutes -= 60;
    }

    $checkIn = sprintf('%s %02d:%02d:00', $date, $checkInHour, $checkInMinutes);
    $checkOut = sprintf('%s %02d:%02d:00', $date, 16 + ($i % 3), ($i * 7) % 60);

    AttendanceRecord::query()->updateOrCreate(
        [
            'employee_id' => $employee->id,
            'work_date' => $date,
        ],
        [
            'scheduled_start_time' => '08:22:00',
            'checked_in_at' => $checkIn,
            'checked_out_at' => $checkOut,
            'late_minutes' => $late,
        ],
    );
}

echo "Created 30 attendance records for {$employee->name} (id: {$employee->id}).\n";
