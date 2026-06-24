<?php

namespace App\Services;

use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\EmployeeDailyOff;
use App\Models\Setting;
use App\Models\User;
use App\Support\TimeFormatter;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

class AttendanceService
{
    public const LATE_AUTO_DAY_OFF_MINUTES = 30;

    /**
     * @return list<array<string, mixed>>
     */
    public function todayEmployees(): array
    {
        $this->applyAutomaticLateDayOffs();

        $employees = Employee::query()
            ->active()
            ->withCount(['attendanceRecords', 'dailyOffs'])
            ->orderBy('name')
            ->get();

        $today = now()->toDateString();

        $records = AttendanceRecord::query()
            ->whereDate('work_date', $today)
            ->whereIn('employee_id', $employees->pluck('id'))
            ->get()
            ->keyBy('employee_id');

        $dailyOffs = EmployeeDailyOff::query()
            ->whereDate('off_date', $today)
            ->whereIn('employee_id', $employees->pluck('id'))
            ->get()
            ->keyBy('employee_id');

        return $employees->map(function (Employee $employee) use ($records, $dailyOffs): array {
            $record = $records->get($employee->id);
            $dayOff = $dailyOffs->get($employee->id);

            return $this->formatEmployee($employee, $record, $dayOff);
        })->values()->all();
    }

    public function isEmployeeOffToday(Employee $employee): bool
    {
        return EmployeeDailyOff::query()
            ->where('employee_id', $employee->id)
            ->whereDate('off_date', now()->toDateString())
            ->exists();
    }

    public function markDayOff(Employee $employee, User $user): EmployeeDailyOff
    {
        $record = AttendanceRecord::query()
            ->where('employee_id', $employee->id)
            ->whereDate('work_date', now()->toDateString())
            ->first();

        abort_if($record?->checked_in_at !== null, 422, 'لا يمكن تعيين الإجازة بعد تسجيل الحضور.');

        return EmployeeDailyOff::query()->updateOrCreate(
            [
                'employee_id' => $employee->id,
                'off_date' => now()->toDateString(),
            ],
            [
                'created_by' => $user->id,
            ],
        );
    }

    public function unmarkDayOff(Employee $employee): void
    {
        EmployeeDailyOff::query()
            ->where('employee_id', $employee->id)
            ->whereDate('off_date', now()->toDateString())
            ->delete();

        $this->waiveAutomaticDayOffForToday($employee);
    }

    public function checkIn(Employee $employee): AttendanceRecord
    {
        if ($this->isEmployeeOffToday($employee)) {
            abort(422, 'اليوم إجازة لهذا الموظف.');
        }

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

        if ($lateMinutes >= self::LATE_AUTO_DAY_OFF_MINUTES && ! $this->isAutomaticDayOffWaivedForToday($employee)) {
            $this->markAutomaticDayOff($employee);

            abort(422, 'تأخر الموظف نصف ساعة أو أكثر، تم تسجيل اليوم كإجازة.');
        }

        $record->update([
            'checked_in_at' => $checkedInAt,
            'late_minutes' => $lateMinutes,
        ]);

        return $record->fresh();
    }

    public function checkOut(Employee $employee): AttendanceRecord
    {
        if ($this->isEmployeeOffToday($employee)) {
            abort(422, 'اليوم إجازة لهذا الموظف.');
        }

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

    public function applyAutomaticLateDayOffs(): int
    {
        $today = now()->toDateString();
        $cutoff = Carbon::parse("{$today} {$this->scheduledStartTime()}")->addMinutes(self::LATE_AUTO_DAY_OFF_MINUTES);

        if (now()->lt($cutoff)) {
            return 0;
        }

        $employeeIds = Employee::query()->active()->pluck('id');

        if ($employeeIds->isEmpty()) {
            return 0;
        }

        $checkedInEmployeeIds = AttendanceRecord::query()
            ->whereDate('work_date', $today)
            ->whereIn('employee_id', $employeeIds)
            ->whereNotNull('checked_in_at')
            ->pluck('employee_id');

        $created = 0;

        foreach ($employeeIds->diff($checkedInEmployeeIds) as $employeeId) {
            if ($this->hasAutomaticDayOffWaiver($employeeId)) {
                continue;
            }

            if ($this->findTodayDayOff($employeeId) !== null) {
                continue;
            }

            EmployeeDailyOff::query()->create([
                'employee_id' => $employeeId,
                'off_date' => $today,
                'created_by' => null,
            ]);

            $created++;
        }

        return $created;
    }

    /**
     * @return array<string, mixed>
     */
    public function formatEmployee(Employee $employee, ?AttendanceRecord $record, ?EmployeeDailyOff $dayOff = null): array
    {
        return [
            'id' => $employee->id,
            'name' => $employee->name,
            'check_in' => TimeFormatter::formatTimeOrDefault($record?->checked_in_at),
            'check_out' => TimeFormatter::formatTimeOrDefault($record?->checked_out_at),
            'late_minutes' => $record?->late_minutes,
            'has_checked_in' => $record?->checked_in_at !== null,
            'has_checked_out' => $record?->checked_out_at !== null,
            'is_day_off' => $dayOff !== null,
            'is_automatic_day_off' => $dayOff !== null && $dayOff->created_by === null,
            'can_manage_day_off' => ! ($record?->checked_in_at !== null),
            'attendance_records_count' => $employee->attendance_records_count + $employee->daily_offs_count,
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
        $attendanceRecords = $employee->attendanceRecords()
            ->orderByDesc('work_date')
            ->orderByDesc('id')
            ->limit(60)
            ->get();

        $dailyOffs = $employee->dailyOffs()
            ->orderByDesc('off_date')
            ->orderByDesc('id')
            ->limit(60)
            ->get()
            ->keyBy(fn (EmployeeDailyOff $off): string => $off->off_date->toDateString());

        $entries = $attendanceRecords->map(function (AttendanceRecord $record) use ($dailyOffs): array {
            $workDate = $record->work_date->toDateString();
            $dayOff = $dailyOffs->get($workDate);

            return [
                ...$this->formatAttendanceHistoryRecord($record),
                'is_day_off' => $dayOff !== null,
                'is_automatic_day_off' => $dayOff !== null && $dayOff->created_by === null,
                'sort_date' => $workDate,
            ];
        });

        $attendanceDates = $attendanceRecords
            ->map(fn (AttendanceRecord $record): string => $record->work_date->toDateString())
            ->flip();

        $offOnlyEntries = $dailyOffs
            ->reject(fn (EmployeeDailyOff $off): bool => $attendanceDates->has($off->off_date->toDateString()))
            ->map(fn (EmployeeDailyOff $off): array => [
                ...$this->formatDayOffHistoryRecord($off),
                'sort_date' => $off->off_date->toDateString(),
            ]);

        $records = $entries
            ->concat($offOnlyEntries)
            ->sortByDesc('sort_date')
            ->take(60)
            ->map(fn (array $record): array => collect($record)->except('sort_date')->all())
            ->values()
            ->all();

        return [
            'employee_id' => $employee->id,
            'employee_name' => $employee->name,
            'records_count' => $employee->attendanceRecords()->count() + $employee->dailyOffs()->count(),
            'records' => $records,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formatAttendanceHistoryRecord(AttendanceRecord $record): array
    {
        return [
            'id' => $record->id,
            'work_date' => TimeFormatter::formatDate($record->work_date),
            'work_day_name' => TimeFormatter::formatArabicWeekday($record->work_date),
            'scheduled_start' => TimeFormatter::formatTimeOrDefault($record->scheduled_start_time),
            'checked_in_at' => TimeFormatter::formatTimeOrDefault($record->checked_in_at),
            'checked_out_at' => TimeFormatter::formatTimeOrDefault($record->checked_out_at),
            'late_label' => $record->checked_in_at !== null
                ? (TimeFormatter::formatLateMinutes($record->late_minutes) ?? '—')
                : '—',
            'is_day_off' => false,
            'is_automatic_day_off' => false,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formatDayOffHistoryRecord(EmployeeDailyOff $off): array
    {
        $isAutomatic = $off->created_by === null;

        return [
            'id' => -$off->id,
            'work_date' => TimeFormatter::formatDate($off->off_date),
            'work_day_name' => TimeFormatter::formatArabicWeekday($off->off_date),
            'scheduled_start' => '—',
            'checked_in_at' => '—',
            'checked_out_at' => '—',
            'late_label' => 'إجازة',
            'is_day_off' => true,
            'is_automatic_day_off' => $isAutomatic,
        ];
    }

    private function markAutomaticDayOff(Employee $employee): EmployeeDailyOff
    {
        $existing = $this->findTodayDayOff($employee->id);

        if ($existing !== null) {
            return $existing;
        }

        return EmployeeDailyOff::query()->create([
            'employee_id' => $employee->id,
            'off_date' => now()->toDateString(),
            'created_by' => null,
        ]);
    }

    private function findTodayDayOff(int $employeeId): ?EmployeeDailyOff
    {
        return EmployeeDailyOff::query()
            ->where('employee_id', $employeeId)
            ->whereDate('off_date', now()->toDateString())
            ->first();
    }

    private function automaticDayOffWaiverCacheKey(Employee|int $employee): string
    {
        $employeeId = $employee instanceof Employee ? $employee->id : $employee;

        return 'attendance.auto_day_off_waived.'.$employeeId.'.'.now()->toDateString();
    }

    private function waiveAutomaticDayOffForToday(Employee $employee): void
    {
        Cache::put(
            $this->automaticDayOffWaiverCacheKey($employee),
            true,
            now()->endOfDay(),
        );
    }

    private function isAutomaticDayOffWaivedForToday(Employee $employee): bool
    {
        return $this->hasAutomaticDayOffWaiver($employee->id);
    }

    private function hasAutomaticDayOffWaiver(int $employeeId): bool
    {
        return Cache::get($this->automaticDayOffWaiverCacheKey($employeeId), false) === true;
    }

    private function scheduledStartTime(): string
    {
        $schedule = Cache::remember(
            'settings.work_schedule',
            now()->addHour(),
            fn (): array => Setting::getValue('work_schedule', ['start_time' => '08:22']) ?? ['start_time' => '08:22'],
        );

        $startTime = is_array($schedule) ? ($schedule['start_time'] ?? '08:22') : '08:22';

        return strlen($startTime) === 5 ? "{$startTime}:00" : $startTime;
    }
}
