<?php

use App\Http\Controllers\BonusProgramController;
use App\Http\Controllers\CreditController;
use App\Http\Controllers\DeviceRegistrationController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\ManagementController;
use App\Http\Controllers\SessionDurationController;
use App\Http\Controllers\TrustedDeviceController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WorkScheduleController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('invoices.active');
})->name('home');

Route::redirect('dashboard', '/invoices/active');

Route::get('device/register', [DeviceRegistrationController::class, 'create'])->name('device.register');
Route::post('device/register', [DeviceRegistrationController::class, 'store'])->name('device.register.store');

Route::middleware(['auth', 'active', 'trusted'])->group(function () {
    Route::get('invoices/active', [InvoiceController::class, 'active'])->name('invoices.active');
    Route::get('invoices/active/changes', [InvoiceController::class, 'activeChanges'])->name('invoices.active.changes');
    Route::get('invoices/completed', [InvoiceController::class, 'completed'])->name('invoices.completed');
    Route::get('invoices/pending-records', [InvoiceController::class, 'pendingRecordsForPhone'])
        ->middleware('admin')
        ->name('invoices.pending-records');
    Route::get('invoices/previous-visits', [InvoiceController::class, 'previousVisitsForPhone'])
        ->middleware('admin')
        ->name('invoices.previous-visits');
    Route::get('invoices/bonuses', [InvoiceController::class, 'pendingBonuses'])->name('invoices.bonuses');
    Route::get('invoices/credits', [CreditController::class, 'index'])->name('invoices.credits');
    Route::post('credits', [CreditController::class, 'store'])->name('credits.store');
    Route::post('customer-credits/{customerCredit}/activate', [CreditController::class, 'activate'])->name('customer-credits.activate');
    Route::patch('customer-credits/{customerCredit}', [CreditController::class, 'update'])
        ->middleware('admin')
        ->name('customer-credits.update');
    Route::delete('customer-credits/{customerCredit}', [CreditController::class, 'destroy'])
        ->middleware('admin')
        ->name('customer-credits.destroy');
    Route::post('invoices', [InvoiceController::class, 'store'])->name('invoices.store');
    Route::get('invoices/{invoice}', [InvoiceController::class, 'show'])->name('invoices.show');
    Route::patch('invoices/{invoice}', [InvoiceController::class, 'update'])->name('invoices.update');
    Route::get('invoices/{invoice}/visits', [InvoiceController::class, 'visits'])->name('invoices.visits');
    Route::post('invoices/{invoice}/bonuses', [InvoiceController::class, 'storeBonus'])->name('invoices.bonuses.store');
    Route::post('customer-bonuses/{customerBonus}/activate', [InvoiceController::class, 'activateCustomerBonus'])->name('customer-bonuses.activate');
    Route::get('customer-bonuses/{customerBonus}', [InvoiceController::class, 'showExpiredBonus'])->name('customer-bonuses.show');
    Route::get('customer-bonuses/{customerBonus}/visits', [InvoiceController::class, 'expiredBonusVisits'])->name('customer-bonuses.visits');
    Route::patch('invoices/{invoice}/checkout', [InvoiceController::class, 'checkout'])->name('invoices.checkout');
    Route::patch('invoices/{invoice}/reactivate', [InvoiceController::class, 'reactivate'])->name('invoices.reactivate');
    Route::delete('invoices/{invoice}/completed-record', [InvoiceController::class, 'destroyCompleted'])
        ->middleware('admin')
        ->name('invoices.completed-record.destroy');
    Route::delete('customer-bonuses/{customerBonus}/completed-record', [InvoiceController::class, 'destroyExpiredBonus'])
        ->middleware('admin')
        ->name('customer-bonuses.completed-record.destroy');
    Route::post('invoices/{invoice}/extensions', [InvoiceController::class, 'extend'])->name('invoices.extend');

    Route::get('employees', [EmployeeController::class, 'index'])->name('employees.index');
    Route::get('employees/{employee}/attendance-history', [EmployeeController::class, 'attendanceHistory'])
        ->middleware('admin')
        ->name('employees.attendance-history');
    Route::post('employees/{employee}/check-in', [EmployeeController::class, 'checkIn'])->name('employees.check-in');
    Route::post('employees/{employee}/check-out', [EmployeeController::class, 'checkOut'])->name('employees.check-out');
    Route::post('employees/{employee}/mark-day-off', [EmployeeController::class, 'markDayOff'])
        ->middleware('admin')
        ->name('employees.mark-day-off');
    Route::delete('employees/{employee}/mark-day-off', [EmployeeController::class, 'unmarkDayOff'])
        ->middleware('admin')
        ->name('employees.unmark-day-off');

    Route::middleware('admin')->group(function () {
        Route::get('management', [ManagementController::class, 'index'])->name('management.index');
        Route::post('management/session-durations', [SessionDurationController::class, 'store'])->name('management.session-durations.store');
        Route::patch('management/session-durations/{sessionDuration}', [SessionDurationController::class, 'update'])->name('management.session-durations.update');
        Route::post('management/employees', [EmployeeController::class, 'store'])->name('management.employees.store');
        Route::patch('management/employees/{employee}', [EmployeeController::class, 'update'])->name('management.employees.update');
        Route::delete('management/employees/{employee}', [EmployeeController::class, 'destroy'])->name('management.employees.destroy');
        Route::patch('management/work-schedule', [WorkScheduleController::class, 'update'])->name('management.work-schedule.update');
        Route::patch('management/bonus-program', [BonusProgramController::class, 'update'])->name('management.bonus-program.update');
        Route::post('management/users', [UserController::class, 'store'])->name('management.users.store');
        Route::patch('management/users/{user}', [UserController::class, 'update'])->name('management.users.update');
        Route::post('management/device-codes', [TrustedDeviceController::class, 'storeCode'])->name('management.device-codes.store');
        Route::patch('management/device-trust', [TrustedDeviceController::class, 'updateSettings'])->name('management.device-trust.update');
        Route::patch('management/attendance-device-restriction', [TrustedDeviceController::class, 'updateAttendanceRestriction'])->name('management.attendance-device-restriction.update');
        Route::patch('management/trusted-devices/{trustedDevice}', [TrustedDeviceController::class, 'update'])->name('management.trusted-devices.update');
        Route::delete('management/trusted-devices/{trustedDevice}', [TrustedDeviceController::class, 'destroy'])->name('management.trusted-devices.destroy');
        Route::post('management/prune-completed-invoices', [ManagementController::class, 'pruneCompletedInvoices'])->name('management.prune-completed-invoices');
    });
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
