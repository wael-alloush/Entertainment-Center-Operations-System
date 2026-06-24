<?php

use App\Enums\InvoiceStatus;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\SessionDuration;
use App\Models\User;
use App\Services\InvoiceService;
use Database\Seeders\SessionDurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(SessionDurationSeeder::class);
    $this->admin = User::factory()->admin()->create();
    $this->reception = User::factory()->reception()->create();
    $this->durationId = SessionDuration::query()->value('id');
});

it('denies non-admin users from pruning completed invoices', function (): void {
    $this->actingAs($this->reception)
        ->post(route('management.prune-completed-invoices'), [
            'confirmation' => 'حذف الفواتير',
        ])
        ->assertForbidden();
});

it('requires confirmation phrase to prune completed invoices', function (): void {
    $this->actingAs($this->admin)
        ->from(route('management.index'))
        ->post(route('management.prune-completed-invoices'), [
            'confirmation' => 'خطأ',
        ])
        ->assertRedirect()
        ->assertSessionHasErrors('confirmation');
});

it('keeps completed invoices from the last four visit days per customer', function (): void {
    $customer = Customer::factory()->create([
        'phone_number' => '0599000001',
        'visit_count' => 6,
    ]);

    foreach (range(0, 5) as $daysAgo) {
        Invoice::factory()->completed()->create([
            'customer_id' => $customer->id,
            'phone_number' => $customer->phone_number,
            'session_duration_id' => $this->durationId,
            'entered_at' => now()->subDays($daysAgo),
            'exited_at' => now()->subDays($daysAgo)->addHour(),
        ]);
    }

    $result = app(InvoiceService::class)->pruneOldCompletedInvoices();

    expect($result['deleted'])->toBe(2)
        ->and($result['kept'])->toBe(4)
        ->and(Invoice::query()->completed()->where('phone_number', $customer->phone_number)->count())->toBe(4)
        ->and($customer->fresh()->visit_count)->toBe(6);
});

it('does not delete active invoices during cleanup', function (): void {
    $customer = Customer::factory()->create([
        'phone_number' => '0599000002',
        'visit_count' => 2,
    ]);

    foreach (range(5, 2) as $daysAgo) {
        Invoice::factory()->completed()->create([
            'customer_id' => $customer->id,
            'phone_number' => $customer->phone_number,
            'session_duration_id' => $this->durationId,
            'entered_at' => now()->subDays($daysAgo),
            'exited_at' => now()->subDays($daysAgo)->addHour(),
        ]);
    }

    $activeInvoice = Invoice::factory()->active()->create([
        'customer_id' => $customer->id,
        'phone_number' => $customer->phone_number,
        'session_duration_id' => $this->durationId,
        'entered_at' => now(),
    ]);

    app(InvoiceService::class)->pruneOldCompletedInvoices();

    expect(Invoice::query()->find($activeInvoice->id))->not->toBeNull()
        ->and(Invoice::query()->completed()->where('phone_number', $customer->phone_number)->count())->toBe(3)
        ->and($customer->fresh()->visit_count)->toBe(2);
});

it('physically deletes cancelled invoices during cleanup', function (): void {
    $invoice = Invoice::factory()->create([
        'status' => InvoiceStatus::Cancelled,
        'session_duration_id' => $this->durationId,
    ]);

    app(InvoiceService::class)->pruneOldCompletedInvoices();

    expect(Invoice::query()->find($invoice->id))->toBeNull();
});

it('allows admin to prune completed invoices from management', function (): void {
    $customer = Customer::factory()->create([
        'phone_number' => '0599000003',
        'visit_count' => 5,
    ]);

    foreach (range(0, 4) as $daysAgo) {
        Invoice::factory()->completed()->create([
            'customer_id' => $customer->id,
            'phone_number' => $customer->phone_number,
            'session_duration_id' => $this->durationId,
            'entered_at' => now()->subDays($daysAgo),
            'exited_at' => now()->subDays($daysAgo)->addHour(),
        ]);
    }

    $this->actingAs($this->admin)
        ->post(route('management.prune-completed-invoices'), [
            'confirmation' => 'حذف الفواتير',
        ])
        ->assertRedirect(route('management.index'))
        ->assertSessionHas('success');

    expect(Invoice::query()->completed()->where('phone_number', $customer->phone_number)->count())->toBe(4)
        ->and($customer->fresh()->visit_count)->toBe(5);
});

it('shows invoice cleanup stats on the management page', function (): void {
    Invoice::factory()->completed()->count(2)->create([
        'session_duration_id' => $this->durationId,
    ]);

    Invoice::factory()->create([
        'status' => InvoiceStatus::Cancelled,
        'session_duration_id' => $this->durationId,
    ]);

    $this->actingAs($this->admin)
        ->get(route('management.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('management/index')
            ->where('invoiceCleanup.completed_invoices_count', 2)
            ->where('invoiceCleanup.cancelled_invoices_count', 1));
});
