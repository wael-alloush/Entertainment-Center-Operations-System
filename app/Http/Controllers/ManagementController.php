<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\SessionDuration;
use App\Models\Setting;
use Inertia\Inertia;
use Inertia\Response;

class ManagementController extends Controller
{
    public function index(): Response
    {
        $workSchedule = Setting::getValue('work_schedule', ['start_time' => '08:22']);
        $startTime = is_array($workSchedule) ? ($workSchedule['start_time'] ?? '08:22') : '08:22';

        return Inertia::render('management/index', [
            'sessionDurations' => SessionDuration::query()
                ->withCount('invoices')
                ->orderBy('sort_order')
                ->get()
                ->map(fn (SessionDuration $duration): array => [
                    'id' => $duration->id,
                    'name' => $duration->name,
                    'duration_minutes' => $duration->duration_minutes,
                    'color' => $duration->color,
                    'sort_order' => $duration->sort_order,
                    'is_active' => $duration->is_active,
                    'is_open' => $duration->isOpen(),
                    'invoices_count' => $duration->invoices_count,
                ]),
            'employees' => Employee::query()
                ->withCount('attendanceRecords')
                ->orderBy('name')
                ->get()
                ->map(fn (Employee $employee): array => [
                    'id' => $employee->id,
                    'name' => $employee->name,
                    'is_active' => $employee->is_active,
                    'attendance_records_count' => $employee->attendance_records_count,
                ]),
            'workSchedule' => [
                'start_time' => strlen($startTime) === 8 ? substr($startTime, 0, 5) : $startTime,
            ],
        ]);
    }
}
