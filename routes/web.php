<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\ManagementController;
use App\Http\Controllers\SessionDurationController;
use App\Http\Controllers\WorkScheduleController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('dashboard');
})->name('home');

Route::middleware(['auth'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('invoices/active', [InvoiceController::class, 'active'])->name('invoices.active');
    Route::get('invoices/active/changes', [InvoiceController::class, 'activeChanges'])->name('invoices.active.changes');
    Route::get('invoices/completed', [InvoiceController::class, 'completed'])->name('invoices.completed');
    Route::post('invoices', [InvoiceController::class, 'store'])->name('invoices.store');
    Route::get('invoices/{invoice}', [InvoiceController::class, 'show'])->name('invoices.show');
    Route::patch('invoices/{invoice}', [InvoiceController::class, 'update'])->name('invoices.update');
    Route::get('invoices/{invoice}/visits', [InvoiceController::class, 'visits'])->name('invoices.visits');
    Route::post('invoices/{invoice}/bonuses', [InvoiceController::class, 'storeBonus'])->name('invoices.bonuses.store');
    Route::patch('invoices/{invoice}/checkout', [InvoiceController::class, 'checkout'])->name('invoices.checkout');
    Route::patch('invoices/{invoice}/parent-call', [InvoiceController::class, 'toggleParentCall'])->name('invoices.parent-call');
    Route::patch('invoices/{invoice}/reactivate', [InvoiceController::class, 'reactivate'])->name('invoices.reactivate');
    Route::post('invoices/{invoice}/extensions', [InvoiceController::class, 'extend'])->name('invoices.extend');

    Route::get('employees', [EmployeeController::class, 'index'])->name('employees.index');
    Route::get('employees/{employee}/attendance-history', [EmployeeController::class, 'attendanceHistory'])
        ->middleware('admin')
        ->name('employees.attendance-history');
    Route::post('employees/{employee}/check-in', [EmployeeController::class, 'checkIn'])->name('employees.check-in');
    Route::post('employees/{employee}/check-out', [EmployeeController::class, 'checkOut'])->name('employees.check-out');

    Route::middleware('admin')->group(function () {
        Route::get('management', [ManagementController::class, 'index'])->name('management.index');
        Route::post('management/session-durations', [SessionDurationController::class, 'store'])->name('management.session-durations.store');
        Route::patch('management/session-durations/{sessionDuration}', [SessionDurationController::class, 'update'])->name('management.session-durations.update');
        Route::post('management/employees', [EmployeeController::class, 'store'])->name('management.employees.store');
        Route::patch('management/employees/{employee}', [EmployeeController::class, 'update'])->name('management.employees.update');
        Route::patch('management/work-schedule', [WorkScheduleController::class, 'update'])->name('management.work-schedule.update');
    });
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
