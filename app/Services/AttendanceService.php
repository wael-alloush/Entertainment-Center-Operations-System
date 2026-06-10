<?php

namespace App\Services;

use App\Support\TimeFormatter;
use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class AttendanceService
{
    /**
     * @return list<array<string, mixed>>
     */
    public function todayEmployees(): array
    {
        $employees = Employee::query()
            ->active()
            ->withCount('attendanceRecords')
            ->orderBy('name')
            ->get();

        $records = AttendanceRecord::query()
            ->whereDate('work_date', now()->toDateString())
            ->whereIn('employee_id', $employees->pluck('id'))
            ->get()
            ->keyBy('employee_id');

        return $employees->map(function (Employee $employee) use ($records): array {
            $record = $records->get($employee->id);

            return $this->formatEmployee($employee, $record);
        })->values()->all();
    }

    public function checkIn(Employee $employee): AttendanceRecord
    {
        $today = now()->toDateString();
        $startTime = $this->scheduledStartTime();

        $record = AttendanceRecord::query()->firstOrCreate(
            [
                'employee_id' => $employee->id,
                'work_date' => $today,
            ],
            [
                'scheduled_start_time' => $startTime,
            ],
        );

        if ($record->checked_in_at !== null) {
            return $record;
        }

        $checkedInAt = now();
        $scheduledAt = Carbon::parse("{$today} {$startTime}");
        $lateMinutes = (int) $scheduledAt->diffInMinutes($checkedInAt, false);

        $record->update([
            'checked_in_at' => $checkedInAt,
            'late_minutes' => $lateMinutes,
        ]);

        return $record->fresh();
    }

    public function checkOut(Employee $employee): AttendanceRecord
    {
        $record = AttendanceRecord::query()
            ->where('employee_id', $employee->id)
            ->whereDate('work_date', now()->toDateString())
            ->firstOrFail();

        if ($record->checked_in_at === null || $record->checked_out_at !== null) {
            return $record;
        }

        $record->update(['checked_out_at' => now()]);

        return $record->fresh();
    }

    /**
     * @return array<string, mixed>
     */
    public function formatEmployee(Employee $employee, ?AttendanceRecord $record): array
    {
        return [
            'id' => $employee->id,
            'name' => $employee->name,
            'check_in' => TimeFormatter::formatTimeOrDefault($record?->checked_in_at),
            'check_out' => TimeFormatter::formatTimeOrDefault($record?->checked_out_at),
            'late_minutes' => $record?->late_minutes,
            'has_checked_in' => $record?->checked_in_at !== null,
            'has_checked_out' => $record?->checked_out_at !== null,
            'attendance_records_count' => $employee->attendance_records_count,
        ];
    }

    /**
     * @return array{
     *     employee_id: int,
     *     employee_name: string,
     *     records_count: int,
     *     records: list<array<string, mixed>>
     * }
     */
    public function formatAttendanceHistory(Employee $employee): array
    {
        $records = $employee->attendanceRecords()
            ->orderByDesc('work_date')
            ->orderByDesc('id')
            ->limit(60)
            ->get();

        return [
            'employee_id' => $employee->id,
            'employee_name' => $employee->name,
            'records_count' => $employee->attendanceRecords()->count(),
            'records' => $records->map(fn (AttendanceRecord $record): array => [
                'id' => $record->id,
                'work_date' => TimeFormatter::formatDate($record->work_date),
                'scheduled_start' => TimeFormatter::formatTimeOrDefault($record->scheduled_start_time),
                'checked_in_at' => TimeFormatter::formatTimeOrDefault($record->checked_in_at),
                'checked_out_at' => TimeFormatter::formatTimeOrDefault($record->checked_out_at),
                'late_label' => $record->checked_in_at !== null
                    ? (TimeFormatter::formatLateMinutes($record->late_minutes) ?? '—')
                    : '—',
            ])->values()->all(),
        ];
    }

    private function scheduledStartTime(): string
    {
        $schedule = \Illuminate\Support\Facades\Cache::remember(
            'settings.work_schedule',
            now()->addHour(),
            fn (): array => Setting::getValue('work_schedule', ['start_time' => '08:22']) ?? ['start_time' => '08:22'],
        );

        $startTime = is_array($schedule) ? ($schedule['start_time'] ?? '08:22') : '08:22';

        return strlen($startTime) === 5 ? "{$startTime}:00" : $startTime;
    }
}
