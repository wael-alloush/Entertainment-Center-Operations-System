<?php

use App\Enums\InvoiceStatus;
use App\Models\Customer;
use App\Models\CustomerCredit;
use App\Models\Invoice;
use App\Models\SessionDuration;
use App\Models\Setting;
use App\Models\User;
use App\Services\CreditProgramService;
use App\Services\InvoiceService;
use Database\Seeders\SessionDurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(SessionDurationSeeder::class);
    $this->admin = User::factory()->admin()->create();
    $this->reception = User::factory()->reception()->create();
    $this->sessionDurationId = SessionDuration::query()->value('id');
});

it('cycles credit display numbers from 1 to 100', function (): void {
    Setting::setValue('credit_program', ['next_display_number' => 100]);
    Cache::forget('settings.credit_program');

    $service = app(CreditProgramService::class);

    $createPendingCredit = function (string $phoneNumber) use ($service): CustomerCredit {
        return $service->addCredit([
            'phone_number' => $phoneNumber,
            'children_count' => 1,
            'session_duration_id' => $this->sessionDurationId,
            'counts_as_visit' => true,
        ], $this->admin);
    };

    $first = $createPendingCredit('0599111001');

    $this->actingAs($this->reception)
        ->post(route('customer-credits.activate', $first))
        ->assertRedirect(route('invoices.active'));

    expect(Invoice::query()->find($first->fresh()->invoice_id)?->credit_display_number)->toBe(100);

    $second = $createPendingCredit('0599111002');

    $this->actingAs($this->reception)
        ->post(route('customer-credits.activate', $second))
        ->assertRedirect(route('invoices.active'));

    expect(Invoice::query()->find($second->fresh()->invoice_id)?->credit_display_number)->toBe(1);
});

it('respects counts_as_visit when calculating visit days', function (): void {
    $customer = Customer::factory()->create(['phone_number' => '0599222111']);
    $service = app(CreditProgramService::class);

    $credit = $service->addCredit([
        'phone_number' => $customer->phone_number,
        'children_count' => 2,
        'session_duration_id' => $this->sessionDurationId,
        'counts_as_visit' => false,
    ], $this->admin);

    $this->actingAs($this->reception)
        ->post(route('customer-credits.activate', $credit));

    expect(app(InvoiceService::class)->visitDaysCountForPhone($customer->phone_number))->toBe(0);

    $countingCredit = $service->addCredit([
        'phone_number' => '0599222112',
        'children_count' => 1,
        'session_duration_id' => $this->sessionDurationId,
        'counts_as_visit' => true,
    ], $this->admin);

    $this->actingAs($this->reception)
        ->post(route('customer-credits.activate', $countingCredit));

    expect(app(InvoiceService::class)->visitDaysCountForPhone('0599222112'))->toBe(1);
});

it('shows non counting credit invoices in previous visits without increasing visit count', function (): void {
    Carbon::setTestNow('2026-06-10 12:00:00');

    $customer = Customer::factory()->create(['phone_number' => '0599555666']);

    $current = Invoice::factory()->active()->create([
        'customer_id' => $customer->id,
        'phone_number' => '0599555666',
        'entered_at' => now(),
    ]);

    Invoice::factory()->completed()->create([
        'customer_id' => $customer->id,
        'phone_number' => '0599555666',
        'entered_at' => now()->subDays(3),
        'exited_at' => now()->subDays(3)->addHour(),
        'invoice_number' => '100501',
    ]);

    $credit = app(CreditProgramService::class)->addCredit([
        'phone_number' => '0599555666',
        'children_count' => 2,
        'session_duration_id' => $this->sessionDurationId,
        'counts_as_visit' => false,
    ], $this->admin);

    $this->actingAs($this->reception)
        ->post(route('customer-credits.activate', $credit));

    $creditInvoice = Invoice::query()->findOrFail($credit->fresh()->invoice_id);
    $creditInvoice->update([
        'status' => InvoiceStatus::Completed,
        'entered_at' => now()->subDay(),
        'exited_at' => now()->subDay()->addHour(),
    ]);

    app(InvoiceService::class)->refreshStoredVisitCount('0599555666');

    $this->actingAs($this->admin)
        ->getJson(route('invoices.visits', $current))
        ->assertOk()
        ->assertJsonPath('visit_count', 2)
        ->assertJsonCount(3, 'invoices.data')
        ->assertJsonPath('invoices.data.1.invoice_number', 'رصيد-1')
        ->assertJsonPath('invoices.data.1.invoice_display_label', 'رصيد 1')
        ->assertJsonPath('invoices.data.1.is_credit', true);
});

it('allows multiple pending credits for the same customer', function (): void {
    $service = app(CreditProgramService::class);

    $first = $service->addCredit([
        'phone_number' => '0599333111',
        'children_count' => 1,
        'session_duration_id' => $this->sessionDurationId,
        'counts_as_visit' => true,
    ], $this->admin);

    $second = $service->addCredit([
        'phone_number' => '0599333111',
        'children_count' => 2,
        'session_duration_id' => $this->sessionDurationId,
        'counts_as_visit' => false,
    ], $this->admin);

    expect($first->id)->not->toBe($second->id)
        ->and(CustomerCredit::query()->pending()->where('customer_id', $first->customer_id)->count())->toBe(2);
});
