<?php

namespace App\Http\Controllers;

use App\Http\Requests\DeleteEmployeeRequest;
use App\Http\Requests\ManageEmployeeDayOffRequest;
use App\Http\Requests\StoreEmployeeRequest;
use App\Http\Requests\UpdateEmployeeRequest;
use App\Models\Employee;
use App\Services\AttendanceService;
use App\Services\DeviceTrustService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

class EmployeeController extends Controller
{
    public function __construct(
        private AttendanceService $attendanceService,
        private DeviceTrustService $deviceTrustService,
    ) {}

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
            'canRecordAttendance' => $this->deviceTrustService->canRecordAttendance(
                $request,
                $request->user()?->isAdmin() ?? false,
            ),
            'attendanceDeviceRestrictionEnabled' => $this->deviceTrustService->isAttendanceRestrictionEnabled(),
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

    public function destroy(DeleteEmployeeRequest $request, Employee $employee): RedirectResponse
    {
        $name = $employee->name;
        $employee->delete();

        return back()->with('success', "تم حذف الموظف «{$name}» نهائياً مع سجلات حضوره.");
    }

    public function checkIn(Request $request, Employee $employee): RedirectResponse
    {
        if (! $this->deviceTrustService->canRecordAttendance($request, $request->user()?->isAdmin() ?? false)) {
            return back()->with('error', 'تسجيل الحضور متاح فقط من الأجهزة المعتمدة للحضور.');
        }

        if (! $employee->is_active) {
            return back()->with('error', 'لا يمكن تسجيل حضور موظف معطّل أو مفصول.');
        }

        if ($this->attendanceService->isEmployeeOffToday($employee)) {
            return back()->with('error', 'اليوم إجازة لهذا الموظف.');
        }

        try {
            $this->attendanceService->checkIn($employee);
        } catch (HttpException $exception) {
            if ($exception->getStatusCode() === 422) {
                return back()->with('error', $exception->getMessage());
            }

            throw $exception;
        }

        return back()->with('success', 'تم تسجيل الحضور.');
    }

    public function checkOut(Request $request, Employee $employee): RedirectResponse
    {
        if (! $this->deviceTrustService->canRecordAttendance($request, $request->user()?->isAdmin() ?? false)) {
            return back()->with('error', 'تسجيل الانصراف متاح فقط من الأجهزة المعتمدة للحضور.');
        }

        if (! $employee->is_active) {
            return back()->with('error', 'لا يمكن تسجيل انصراف موظف معطّل أو مفصول.');
        }

        if ($this->attendanceService->isEmployeeOffToday($employee)) {
            return back()->with('error', 'اليوم إجازة لهذا الموظف.');
        }

        $this->attendanceService->checkOut($employee);

        return back()->with('success', 'تم تسجيل الانصراف.');
    }

    public function attendanceHistory(Request $request, Employee $employee): JsonResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        return response()->json($this->attendanceService->formatAttendanceHistory($employee));
    }

    public function markDayOff(ManageEmployeeDayOffRequest $request, Employee $employee): RedirectResponse
    {
        if (! $employee->is_active) {
            return back()->with('error', 'لا يمكن تعيين إجازة لموظف معطّل أو مفصول.');
        }

        $this->attendanceService->markDayOff($employee, $request->user());

        return back()->with('success', 'تم تعيين الإجازة للموظف.');
    }

    public function unmarkDayOff(ManageEmployeeDayOffRequest $request, Employee $employee): RedirectResponse
    {
        $this->attendanceService->unmarkDayOff($employee);

        return back()->with('success', 'تم إلغاء الإجازة للموظف.');
    }
}
