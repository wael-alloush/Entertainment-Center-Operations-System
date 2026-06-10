<?php

use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\SessionDuration;
use App\Models\Setting;
use App\Models\User;
use App\Services\AttendanceService;
use Database\Seeders\EmployeeSeeder;
use Database\Seeders\SessionDurationSeeder;
use Illuminate\Support\Facades\Cache;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

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
