<?php

use App\Enums\InvoiceStatus;
use App\Models\AttendanceRecord;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\Invoice;
use App\Models\InvoiceExtension;
use App\Models\SessionDuration;
use App\Models\Setting;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\SessionDurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it('creates all domain tables', function (): void {
    expect(Schema::hasTable('session_durations'))->toBeTrue()
        ->and(Schema::hasTable('customers'))->toBeTrue()
        ->and(Schema::hasTable('employees'))->toBeTrue()
        ->and(Schema::hasTable('settings'))->toBeTrue()
        ->and(Schema::hasTable('invoices'))->toBeTrue()
        ->and(Schema::hasTable('invoice_extensions'))->toBeTrue()
        ->and(Schema::hasTable('attendance_records'))->toBeTrue();
});

it('seeds session durations from the dashboard design', function (): void {
    $this->seed(SessionDurationSeeder::class);

    expect(SessionDuration::query()->count())->toBe(4)
        ->and(SessionDuration::query()->where('name', 'مفتوح')->first()?->duration_minutes)->toBeNull();
});

it('stores invoices with extensions and searchable fields', function (): void {
    $duration = SessionDuration::factory()->create(['duration_minutes' => 65]);
    $extensionDuration = SessionDuration::factory()->create(['duration_minutes' => 33]);
    $customer = Customer::factory()->create(['phone_number' => '0599123456']);

    $invoice = Invoice::factory()->create([
        'invoice_number' => '123456',
        'customer_id' => $customer->id,
        'phone_number' => '0599123456',
        'session_duration_id' => $duration->id,
        'status' => InvoiceStatus::Active,
    ]);

    InvoiceExtension::query()->create([
        'invoice_id' => $invoice->id,
        'session_duration_id' => $extensionDuration->id,
        'sort_order' => 1,
    ]);

    $found = Invoice::query()
        ->search('123456')
        ->with('extensions.sessionDuration')
        ->first();

    expect($found)->not->toBeNull()
        ->and($found->extensions)->toHaveCount(1)
        ->and($found->status)->toBe(InvoiceStatus::Active);

    $byPhone = Invoice::query()->search('0599')->count();
    expect($byPhone)->toBe(1);
});

it('tracks employee attendance per day', function (): void {
    $employee = Employee::factory()->create(['name' => 'وائل']);

    AttendanceRecord::factory()->create([
        'employee_id' => $employee->id,
        'work_date' => now()->toDateString(),
        'scheduled_start_time' => '08:22:00',
        'checked_in_at' => now(),
    ]);

    expect(AttendanceRecord::query()->where('employee_id', $employee->id)->count())->toBe(1);
});

it('stores work schedule settings', function (): void {
    $this->seed(DatabaseSeeder::class);

    expect(Setting::getValue('work_schedule'))->toBe(['start_time' => '08:22']);
});

it('filters active and completed invoices', function (): void {
    Invoice::factory()->active()->create();
    Invoice::factory()->completed()->create();

    expect(Invoice::query()->active()->count())->toBe(1)
        ->and(Invoice::query()->completed()->count())->toBe(1);
});
