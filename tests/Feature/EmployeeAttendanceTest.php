<?php

use App\Models\Employee;
use App\Models\User;
use App\Services\AttendanceService;
use Database\Seeders\EmployeeSeeder;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(EmployeeSeeder::class);
    $this->user = User::factory()->create();
});

it('shows employees with attendance cards', function (): void {
    $this->actingAs($this->user)
        ->get(route('employees.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('employees/index')
            ->has('employees', 3));
});

it('records employee check in and check out', function (): void {
    $employee = Employee::query()->first();

    $this->actingAs($this->user)
        ->post(route('employees.check-in', $employee))
        ->assertRedirect();

    $employee->refresh();
    $record = $employee->attendanceRecords()->whereDate('work_date', now()->toDateString())->first();

    expect($record)->not->toBeNull()
        ->and($record->checked_in_at)->not->toBeNull();

    $this->actingAs($this->user)
        ->post(route('employees.check-out', $employee))
        ->assertRedirect();

    expect($record->fresh()->checked_out_at)->not->toBeNull();
});

it('hides inactive employees from attendance page while keeping their records', function (): void {
    $employee = Employee::query()->first();

    $this->actingAs($this->user)
        ->post(route('employees.check-in', $employee))
        ->assertRedirect();

    expect($employee->attendanceRecords()->count())->toBe(1);

    $employee->update(['is_active' => false]);

    $this->actingAs($this->user)
        ->get(route('employees.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('employees/index')
            ->has('employees', 2));

    expect($employee->fresh()->attendanceRecords()->count())->toBe(1);
});

it('returns employee attendance history for admin users', function (): void {
    $employee = Employee::query()->first();
    $admin = User::factory()->admin()->create();

    $this->travelTo(now()->setTime(8, 45));

    app(AttendanceService::class)->checkIn($employee);
    app(AttendanceService::class)->checkOut($employee);

    $this->actingAs($admin)
        ->getJson(route('employees.attendance-history', $employee))
        ->assertSuccessful()
        ->assertJsonPath('employee_id', $employee->id)
        ->assertJsonPath('employee_name', $employee->name)
        ->assertJsonPath('records_count', 1)
        ->assertJsonCount(1, 'records')
        ->assertJsonPath('records.0.work_date', now()->toDateString())
        ->assertJsonPath('records.0.late_label', fn (string $label) => str_starts_with($label, 'متأخر'));
});

it('denies employee attendance history for reception users', function (): void {
    $employee = Employee::query()->first();
    $reception = User::factory()->reception()->create();

    $this->actingAs($reception)
        ->getJson(route('employees.attendance-history', $employee))
        ->assertForbidden();
});

it('includes attendance records count on employees page for admin users only', function (): void {
    $employee = Employee::query()->active()->orderBy('name')->first();
    $admin = User::factory()->admin()->create();
    $reception = User::factory()->reception()->create();

    app(AttendanceService::class)->checkIn($employee);

    $this->actingAs($admin)
        ->get(route('employees.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('employees/index')
            ->where('employees.0.id', $employee->id)
            ->where('employees.0.attendance_records_count', 1));

    $this->actingAs($reception)
        ->get(route('employees.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('employees/index')
            ->where('employees.0.id', $employee->id)
            ->missing('employees.0.attendance_records_count'));
});

it('prevents check in and check out for inactive employees', function (): void {
    $employee = Employee::query()->first();
    $employee->update(['is_active' => false]);

    $this->actingAs($this->user)
        ->post(route('employees.check-in', $employee))
        ->assertRedirect()
        ->assertSessionHas('error');

    $this->actingAs($this->user)
        ->post(route('employees.check-out', $employee))
        ->assertRedirect()
        ->assertSessionHas('error');

    expect($employee->attendanceRecords()->count())->toBe(0);
});
