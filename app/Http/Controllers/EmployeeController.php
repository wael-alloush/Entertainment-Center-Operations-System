<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEmployeeRequest;
use App\Http\Requests\UpdateEmployeeRequest;
use App\Models\Employee;
use App\Services\AttendanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EmployeeController extends Controller
{
    public function __construct(private AttendanceService $attendanceService) {}

    public function index(Request $request): Response
    {
        $employees = $this->attendanceService->todayEmployees();

        if (! $request->user()?->isAdmin()) {
            $employees = array_map(function (array $employee): array {
                unset($employee['attendance_records_count']);

                return $employee;
            }, $employees);
        }

        return Inertia::render('employees/index', [
            'employees' => $employees,
        ]);
    }

    public function store(StoreEmployeeRequest $request): RedirectResponse
    {
        Employee::query()->create([
            ...$request->validated(),
            'is_active' => $request->boolean('is_active', true),
        ]);

        return back()->with('success', 'تمت إضافة الموظف.');
    }

    public function update(UpdateEmployeeRequest $request, Employee $employee): RedirectResponse
    {
        $employee->update($request->validated());

        $message = $employee->is_active
            ? 'تم تحديث بيانات الموظف.'
            : 'تم تعطيل الموظف. سجلات حضوره السابقة محفوظة ولن يظهر في صفحة الحضور.';

        return back()->with('success', $message);
    }

    public function checkIn(Employee $employee): RedirectResponse
    {
        if (! $employee->is_active) {
            return back()->with('error', 'لا يمكن تسجيل حضور موظف معطّل أو مفصول.');
        }

        $this->attendanceService->checkIn($employee);

        return back()->with('success', 'تم تسجيل الحضور.');
    }

    public function checkOut(Employee $employee): RedirectResponse
    {
        if (! $employee->is_active) {
            return back()->with('error', 'لا يمكن تسجيل انصراف موظف معطّل أو مفصول.');
        }

        $this->attendanceService->checkOut($employee);

        return back()->with('success', 'تم تسجيل الانصراف.');
    }

    public function attendanceHistory(Request $request, Employee $employee): JsonResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        return response()->json($this->attendanceService->formatAttendanceHistory($employee));
    }
}
