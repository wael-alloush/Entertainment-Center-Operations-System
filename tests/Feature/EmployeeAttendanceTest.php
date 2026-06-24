<?php

use App\Models\Employee;
use App\Models\User;
use App\Services\AttendanceService;
use Database\Seeders\EmployeeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses(RefreshDatabase::class);

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

    $this->travelTo(now()->setTime(8, 0));

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

    $this->travelTo(now()->setTime(8, 0));

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

it('includes day off entries in employee attendance history', function (): void {
    $employee = Employee::factory()->create();
    $admin = User::factory()->admin()->create();

    $this->travelTo(now()->setTime(8, 0));

    app(AttendanceService::class)->markDayOff($employee, $admin);

    $this->actingAs($admin)
        ->getJson(route('employees.attendance-history', $employee))
        ->assertSuccessful()
        ->assertJsonPath('records_count', 1)
        ->assertJsonCount(1, 'records')
        ->assertJsonPath('records.0.is_day_off', true)
        ->assertJsonPath('records.0.late_label', 'إجازة')
        ->assertJsonPath('records.0.work_day_name', now()->locale('ar')->translatedFormat('l'))
        ->assertJsonPath('records.0.checked_in_at', '—');
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
        ->assertJsonPath('records.0.work_day_name', now()->locale('ar')->translatedFormat('l'))
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

    $this->travelTo(now()->setTime(8, 0));

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

it('allows admin to mark and unmark day off for today', function (): void {
    $employee = Employee::factory()->create();
    $admin = User::factory()->admin()->create();

    $this->travelTo(now()->setTime(8, 0));

    $this->actingAs($admin)
        ->post(route('employees.mark-day-off', $employee))
        ->assertRedirect()
        ->assertSessionHas('success');

    $this->actingAs($admin)
        ->get(route('employees.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('employees', fn ($employees) => collect($employees)->contains(
                fn (array $row): bool => $row['id'] === $employee->id && $row['is_day_off'] === true,
            )));

    $this->actingAs($this->user)
        ->post(route('employees.check-in', $employee))
        ->assertRedirect()
        ->assertSessionHas('error');

    $this->actingAs($admin)
        ->delete(route('employees.unmark-day-off', $employee))
        ->assertRedirect()
        ->assertSessionHas('success');

    $this->actingAs($this->user)
        ->post(route('employees.check-in', $employee))
        ->assertRedirect()
        ->assertSessionHasNoErrors();
});

it('forbids reception users from managing day off', function (): void {
    $employee = Employee::factory()->create();
    $reception = User::factory()->reception()->create();

    $this->actingAs($reception)
        ->post(route('employees.mark-day-off', $employee))
        ->assertForbidden();
});

it('forbids marking day off after check in', function (): void {
    $employee = Employee::factory()->create();
    $admin = User::factory()->admin()->create();

    $this->travelTo(now()->setTime(8, 0));

    app(AttendanceService::class)->checkIn($employee);

    $this->actingAs($admin)
        ->post(route('employees.mark-day-off', $employee))
        ->assertStatus(422);
});

it('marks automatic day off when employee has not checked in thirty minutes after start', function (): void {
    $employee = Employee::factory()->create();

    $this->travelTo(now()->setTime(8, 52));

    app(AttendanceService::class)->todayEmployees();

    $dayOff = $employee->dailyOffs()->whereDate('off_date', now()->toDateString())->first();

    expect($dayOff)->not->toBeNull()
        ->and($dayOff->created_by)->toBeNull();
});

it('rejects late check in after thirty minutes and marks automatic day off', function (): void {
    $employee = Employee::factory()->create();

    $this->travelTo(now()->setTime(8, 55));

    expect(fn () => app(AttendanceService::class)->checkIn($employee))
        ->toThrow(HttpException::class);

    $record = $employee->attendanceRecords()->whereDate('work_date', now()->toDateString())->first();
    $dayOff = $employee->dailyOffs()->whereDate('off_date', now()->toDateString())->first();

    expect($record?->checked_in_at)->toBeNull()
        ->and($dayOff)->not->toBeNull()
        ->and($dayOff->created_by)->toBeNull();
});

it('returns automatic day off message when checking in late via http', function (): void {
    $employee = Employee::factory()->create();

    $this->travelTo(now()->setTime(8, 55));

    $this->actingAs($this->user)
        ->post(route('employees.check-in', $employee))
        ->assertRedirect()
        ->assertSessionHas('error', 'تأخر الموظف نصف ساعة أو أكثر، تم تسجيل اليوم كإجازة.');
});

it('allows admin to unmark automatic day off', function (): void {
    $employee = Employee::factory()->create();
    $admin = User::factory()->admin()->create();

    $this->travelTo(now()->setTime(8, 52));

    app(AttendanceService::class)->todayEmployees();

    $this->actingAs($admin)
        ->delete(route('employees.unmark-day-off', $employee))
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($employee->dailyOffs()->whereDate('off_date', now()->toDateString())->exists())->toBeFalse();

    app(AttendanceService::class)->todayEmployees();

    expect($employee->dailyOffs()->whereDate('off_date', now()->toDateString())->exists())->toBeFalse();
});

it('allows check in after admin waives automatic day off even when late', function (): void {
    $employee = Employee::factory()->create();

    $this->travelTo(now()->setTime(8, 52));

    app(AttendanceService::class)->todayEmployees();

    app(AttendanceService::class)->unmarkDayOff($employee);

    $this->travelTo(now()->setTime(8, 55));

    $record = app(AttendanceService::class)->checkIn($employee);

    expect($record->checked_in_at)->not->toBeNull()
        ->and($employee->dailyOffs()->whereDate('off_date', now()->toDateString())->exists())->toBeFalse();
});

it('shows automatic day off in attendance history', function (): void {
    $employee = Employee::factory()->create();
    $admin = User::factory()->admin()->create();

    $this->travelTo(now()->setTime(8, 52));

    app(AttendanceService::class)->todayEmployees();

    $this->actingAs($admin)
        ->getJson(route('employees.attendance-history', $employee))
        ->assertSuccessful()
        ->assertJsonPath('records.0.is_day_off', true)
        ->assertJsonPath('records.0.is_automatic_day_off', true)
        ->assertJsonPath('records.0.late_label', 'إجازة');
});
