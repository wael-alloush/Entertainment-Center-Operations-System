<?php

use App\Enums\UserRole;
use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\SessionDuration;
use App\Models\Setting;
use App\Models\User;
use App\Services\AttendanceService;
use Database\Seeders\EmployeeSeeder;
use Database\Seeders\SessionDurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed([SessionDurationSeeder::class, EmployeeSeeder::class]);
    $this->admin = User::factory()->admin()->create();
    $this->reception = User::factory()->reception()->create();

    Setting::query()->updateOrCreate(
        ['key' => 'work_schedule'],
        ['value' => ['start_time' => '08:00']],
    );
});

it('denies non-admin users from management page', function (): void {
    $this->actingAs($this->reception)
        ->get(route('management.index'))
        ->assertForbidden();
});

it('shows management page for admin users', function (): void {
    $this->actingAs($this->admin)
        ->get(route('management.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('management/index')
            ->has('sessionDurations', 4)
            ->has('employees', 3)
            ->has('users', 2)
            ->where('workSchedule.start_time', '08:00'));
});

it('updates only session color including open sessions', function (): void {
    $openDuration = SessionDuration::query()->whereNull('duration_minutes')->first();
    $timedDuration = SessionDuration::query()->whereNotNull('duration_minutes')->first();

    expect($openDuration)->not->toBeNull()
        ->and($timedDuration)->not->toBeNull();

    $this->actingAs($this->admin)
        ->patch(route('management.session-durations.update', $timedDuration), [
            'name' => $timedDuration->name,
            'duration_minutes' => $timedDuration->duration_minutes,
            'color' => '#ff5500',
            'sort_order' => $timedDuration->sort_order,
            'is_active' => true,
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($timedDuration->fresh()->color)->toBe('#ff5500');

    $this->actingAs($this->admin)
        ->patch(route('management.session-durations.update', $openDuration), [
            'name' => $openDuration->name,
            'duration_minutes' => null,
            'color' => '#00aa88',
            'sort_order' => $openDuration->sort_order,
            'is_active' => true,
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($openDuration->fresh()->color)->toBe('#00aa88');
});

it('creates and updates session durations', function (): void {
    $this->actingAs($this->admin)
        ->post(route('management.session-durations.store'), [
            'name' => '45 دقيقة',
            'duration_minutes' => 45,
            'color' => 'var(--primary)',
            'sort_order' => 5,
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $duration = SessionDuration::query()->where('name', '45 دقيقة')->first();
    expect($duration)->not->toBeNull()
        ->and($duration->duration_minutes)->toBe(45);

    $this->actingAs($this->admin)
        ->patch(route('management.session-durations.update', $duration), [
            'name' => '45 دقيقة محدثة',
            'duration_minutes' => 50,
            'color' => 'var(--primary)',
            'sort_order' => 6,
            'is_active' => false,
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($duration->fresh())
        ->name->toBe('45 دقيقة محدثة')
        ->duration_minutes->toBe(50)
        ->is_active->toBeFalse();
});

it('creates and updates employees from management', function (): void {
    $this->actingAs($this->admin)
        ->post(route('management.employees.store'), [
            'name' => 'موظف جديد',
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $employee = Employee::query()->where('name', 'موظف جديد')->first();
    expect($employee)->not->toBeNull()
        ->and($employee->is_active)->toBeTrue();

    $this->actingAs($this->admin)
        ->patch(route('management.employees.update', $employee), [
            'name' => 'موظف محدث',
            'is_active' => false,
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($employee->fresh())
        ->name->toBe('موظف محدث')
        ->is_active->toBeFalse();
});

it('permanently deletes an employee and related attendance records', function (): void {
    $employee = Employee::query()->firstOrFail();

    app(AttendanceService::class)->checkIn($employee);

    expect($employee->attendanceRecords()->count())->toBe(1);

    $this->actingAs($this->admin)
        ->delete(route('management.employees.destroy', $employee))
        ->assertRedirect()
        ->assertSessionHas('success');

    expect(Employee::query()->find($employee->id))->toBeNull()
        ->and(AttendanceRecord::query()->where('employee_id', $employee->id)->count())->toBe(0);
});

it('denies non-admin users from deleting employees', function (): void {
    $employee = Employee::query()->firstOrFail();

    $this->actingAs($this->reception)
        ->delete(route('management.employees.destroy', $employee))
        ->assertForbidden();

    expect(Employee::query()->find($employee->id))->not->toBeNull();
});

it('creates and updates users from management', function (): void {
    $this->actingAs($this->admin)
        ->post(route('management.users.store'), [
            'name' => 'مستخدم جديد',
            'email' => 'new-user@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => UserRole::Reception->value,
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $user = User::query()->where('email', 'new-user@example.com')->first();

    expect($user)->not->toBeNull()
        ->and($user->name)->toBe('مستخدم جديد')
        ->and($user->role)->toBe(UserRole::Reception)
        ->and($user->is_active)->toBeTrue();

    $this->actingAs($this->admin)
        ->patch(route('management.users.update', $user), [
            'name' => 'مستخدم محدث',
            'email' => 'updated-user@example.com',
            'role' => UserRole::Admin->value,
            'is_active' => false,
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($user->fresh())
        ->name->toBe('مستخدم محدث')
        ->email->toBe('updated-user@example.com')
        ->role->toBe(UserRole::Admin)
        ->is_active->toBeFalse();
});

it('prevents admin from disabling their own account', function (): void {
    $this->actingAs($this->admin)
        ->from(route('management.index'))
        ->patch(route('management.users.update', $this->admin), [
            'name' => $this->admin->name,
            'email' => $this->admin->email,
            'role' => UserRole::Admin->value,
            'is_active' => false,
        ])
        ->assertRedirect()
        ->assertSessionHasErrors('is_active');

    expect($this->admin->fresh()->is_active)->toBeTrue();
});

it('updates work schedule and uses it for late minutes', function (): void {
    Cache::forget('settings.work_schedule');

    $this->actingAs($this->admin)
        ->patch(route('management.work-schedule.update'), [
            'start_time' => '09:30',
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    expect(Setting::getValue('work_schedule'))->toBe(['start_time' => '09:30']);

    $employee = Employee::query()->where('is_active', true)->first();

    $this->travelTo(now()->setTime(10, 0));

    app(AttendanceService::class)->checkIn($employee);

    $record = AttendanceRecord::query()
        ->where('employee_id', $employee->id)
        ->whereDate('work_date', now()->toDateString())
        ->first();

    expect($record)->not->toBeNull()
        ->and($record->scheduled_start_time->format('H:i'))->toBe('09:30')
        ->and($record->late_minutes)->toBe(30);
});
