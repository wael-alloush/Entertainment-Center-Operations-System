<?php

namespace App\Http\Controllers;

use App\Http\Requests\PruneCompletedInvoicesRequest;
use App\Models\Employee;
use App\Models\SessionDuration;
use App\Models\Setting;
use App\Models\User;
use App\Services\BonusProgramService;
use App\Services\DeviceTrustService;
use App\Services\InvoiceService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ManagementController extends Controller
{
    public function __construct(
        private BonusProgramService $bonusProgramService,
        private DeviceTrustService $deviceTrustService,
        private InvoiceService $invoiceService,
    ) {}

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
            'bonusProgram' => $this->bonusProgramService->settings(),
            'users' => User::query()
                ->orderBy('name')
                ->get(['id', 'name', 'email', 'role', 'is_active'])
                ->map(fn (User $user): array => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role->value,
                    'role_label' => $user->role->label(),
                    'is_active' => $user->is_active,
                ]),
            'deviceTrust' => $this->deviceTrustService->settings(),
            'attendanceDeviceRestriction' => $this->deviceTrustService->attendanceSettings(),
            'trustedDevices' => $this->deviceTrustService->formatTrustedDevices(),
            'pendingDeviceCodes' => $this->deviceTrustService->formatPendingCodes(),
            'invoiceCleanup' => $this->invoiceService->completedInvoiceCleanupStats(),
        ]);
    }

    public function pruneCompletedInvoices(PruneCompletedInvoicesRequest $request): RedirectResponse
    {
        $result = $this->invoiceService->pruneOldCompletedInvoices();

        return redirect()
            ->route('management.index')
            ->withFragment('maintenance')
            ->with('success', sprintf(
                'تم حذف %d فاتورة. بقي %d فاتورة مكتملة ضمن آخر %d زيارات لكل عميل دون تغيير عدد الزيارات المخزَّن.',
                $result['deleted'],
                $result['kept'],
                InvoiceService::RECENT_VISIT_DAYS_TO_KEEP,
            ));
    }
}
