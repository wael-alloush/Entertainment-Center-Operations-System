<?php

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceExtension;
use App\Models\SessionDuration;
use App\Models\User;
use App\Services\BonusProgramService;
use App\Services\CreditProgramService;
use App\Services\InvoiceService;
use Database\Seeders\SessionDurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(SessionDurationSeeder::class);
    $this->admin = User::factory()->admin()->create();
    $this->reception = User::factory()->reception()->create();
});

it('returns invoice details as json', function (): void {
    $invoice = Invoice::factory()->active()->create();

    $this->actingAs($this->reception)
        ->getJson(route('invoices.show', $invoice))
        ->assertOk()
        ->assertJsonStructure([
            'invoice' => [
                'id',
                'invoice_number',
                'phone_number',
                'visit_count',
                'status_label',
                'bonuses',
            ],
        ]);
});

it('returns previous invoices while counting visits by unique days', function (): void {
    $customer = Customer::factory()->create(['phone_number' => '0599111111']);
    $hour = SessionDuration::query()->where('name', 'ساعة')->first();
    $halfHour = SessionDuration::query()->where('name', 'نصف ساعة')->first();

    $current = Invoice::factory()->active()->create([
        'customer_id' => $customer->id,
        'phone_number' => '0599111111',
        'children_count' => 1,
        'entered_at' => now(),
    ]);

    $sameDayMorning = now()->subDays(1)->setTime(10, 0);
    $sameDayEvening = now()->subDays(1)->setTime(18, 0);

    $dayOneLatest = Invoice::factory()->completed()->create([
        'customer_id' => $customer->id,
        'phone_number' => '0599111111',
        'session_duration_id' => $hour->id,
        'entered_at' => $sameDayEvening,
        'exited_at' => $sameDayEvening->copy()->addHour(),
        'invoice_number' => '100001',
        'children_count' => 2,
    ]);

    Invoice::factory()->completed()->create([
        'customer_id' => $customer->id,
        'phone_number' => '0599111111',
        'session_duration_id' => $hour->id,
        'entered_at' => $sameDayMorning,
        'exited_at' => $sameDayMorning->copy()->addHour(),
        'invoice_number' => '100099',
        'children_count' => 1,
    ]);

    InvoiceExtension::query()->create([
        'invoice_id' => $dayOneLatest->id,
        'session_duration_id' => $halfHour->id,
        'sort_order' => 1,
    ]);

    collect([
        ['invoice_number' => '100002', 'children_count' => 1, 'entered_at' => now()->subDays(2)],
        ['invoice_number' => '100003', 'children_count' => 1, 'entered_at' => now()->subDays(3)],
        ['invoice_number' => '100004', 'children_count' => 1, 'entered_at' => now()->subDays(4)],
        ['invoice_number' => '100005', 'children_count' => 5, 'entered_at' => now()->subDays(5)],
    ])->each(fn (array $attributes): Invoice => Invoice::factory()->completed()->create([
        'customer_id' => $customer->id,
        'phone_number' => '0599111111',
        'session_duration_id' => $hour->id,
        'entered_at' => $attributes['entered_at'],
        'exited_at' => $attributes['entered_at']->copy()->addHour(),
        'invoice_number' => $attributes['invoice_number'],
        'children_count' => $attributes['children_count'],
    ]));

    app(InvoiceService::class)->refreshStoredVisitCount('0599111111');

    $this->actingAs($this->admin)
        ->getJson(route('invoices.visits', $current))
        ->assertOk()
        ->assertJsonPath('phone_number', '0599111111')
        ->assertJsonPath('visit_count', 6)
        ->assertJsonPath('average_children', 1.25)
        ->assertJsonPath('average_children_visit_threshold', 4)
        ->assertJsonCount(7, 'invoices.data')
        ->assertJsonPath('invoices.data.0.invoice_number', $current->invoice_number)
        ->assertJsonPath('invoices.data.1.invoice_number', '100001')
        ->assertJsonPath('invoices.data.1.children_count', 2)
        ->assertJsonPath('invoices.data.1.original_duration_minutes', 65)
        ->assertJsonPath('invoices.data.1.extensions_duration_minutes', 33)
        ->assertJsonPath('invoices.data.1.total_duration_minutes', 98)
        ->assertJsonCount(1, 'invoices.data.1.extensions')
        ->assertJsonPath('invoices.data.1.extensions.0.name', $halfHour->name)
        ->assertJsonPath('invoices.data.2.invoice_number', '100099')
        ->assertJsonPath('invoices.data.6.invoice_number', '100005');
});

it('shows all phone invoices credits and expired bonuses regardless of visit day limit', function (): void {
    Carbon::setTestNow('2026-06-10 12:00:00');

    $customer = Customer::factory()->create(['phone_number' => '0599777888']);
    $sessionDurationId = SessionDuration::query()->value('id');

    $current = Invoice::factory()->active()->create([
        'customer_id' => $customer->id,
        'phone_number' => '0599777888',
        'entered_at' => now(),
    ]);

    Invoice::factory()->completed()->create([
        'customer_id' => $customer->id,
        'phone_number' => '0599777888',
        'entered_at' => now()->subDays(10),
        'exited_at' => now()->subDays(10)->addHour(),
        'invoice_number' => 'OLD001',
    ]);

    $credit = app(CreditProgramService::class)->addCredit([
        'phone_number' => '0599777888',
        'children_count' => 1,
        'session_duration_id' => $sessionDurationId,
        'counts_as_visit' => false,
    ], $this->admin);

    $this->actingAs($this->reception)
        ->post(route('customer-credits.activate', $credit));

    $creditInvoice = Invoice::query()->findOrFail($credit->fresh()->invoice_id);
    $creditInvoice->update([
        'entered_at' => now()->subDays(8),
        'exited_at' => now()->subDays(8)->addHour(),
    ]);
    $this->actingAs($this->reception)->patch(route('invoices.checkout', $creditInvoice));

    $sourceInvoice = Invoice::factory()->completed()->create([
        'customer_id' => $customer->id,
        'phone_number' => '0599777888',
        'entered_at' => now()->subDays(7),
        'exited_at' => now()->subDays(7)->addHour(),
    ]);

    $bonus = app(BonusProgramService::class)->addBonus($sourceInvoice, [
        'children_count' => 2,
        'session_duration_id' => $sessionDurationId,
    ], $this->admin);

    $bonus->update([
        'expired_at' => now()->subDays(6),
        'expires_at' => now()->subDays(6),
    ]);

    app(InvoiceService::class)->refreshStoredVisitCount('0599777888');

    $response = $this->actingAs($this->admin)
        ->getJson(route('invoices.visits', $current))
        ->assertOk()
        ->assertJsonPath('visit_count', 4);

    $invoiceNumbers = collect($response->json('invoices.data'))->pluck('invoice_number');

    expect($invoiceNumbers)->toContain($current->invoice_number)
        ->toContain('OLD001')
        ->toContain('رصيد-1')
        ->toContain('مكافأة منتهية')
        ->and($invoiceNumbers)->toHaveCount(5);
});

it('includes the current invoice in the visits list even when active', function (): void {
    $customer = Customer::factory()->create(['phone_number' => '0599111111']);

    $current = Invoice::factory()->active()->create([
        'customer_id' => $customer->id,
        'phone_number' => '0599111111',
        'invoice_number' => 'CURRENT1',
        'children_count' => 3,
        'entered_at' => now(),
    ]);

    Invoice::factory()->completed()->create([
        'customer_id' => $customer->id,
        'phone_number' => '0599111111',
        'invoice_number' => 'OLD001',
        'entered_at' => now()->subDay(),
        'exited_at' => now()->subDay()->addHour(),
    ]);

    app(InvoiceService::class)->refreshStoredVisitCount('0599111111');

    $this->actingAs($this->admin)
        ->getJson(route('invoices.visits', $current))
        ->assertOk()
        ->assertJsonCount(2, 'invoices.data')
        ->assertJsonPath('invoices.data.0.invoice_number', 'CURRENT1')
        ->assertJsonPath('visit_count', 2);
});

it('shows all active invoices for the same phone regardless of entry point', function (): void {
    Carbon::setTestNow('2026-06-10 12:00:00');

    $customer = Customer::factory()->create(['phone_number' => '0509012348']);
    $sessionDurationId = SessionDuration::query()->value('id');

    $regularInvoice = Invoice::factory()->active()->create([
        'customer_id' => $customer->id,
        'phone_number' => '0509012348',
        'invoice_number' => '100801',
        'entered_at' => now()->subHour(),
    ]);

    $credit = app(CreditProgramService::class)->addCredit([
        'phone_number' => '0509012348',
        'children_count' => 2,
        'session_duration_id' => $sessionDurationId,
        'counts_as_visit' => false,
    ], $this->admin);

    $this->actingAs($this->reception)
        ->post(route('customer-credits.activate', $credit));

    $creditInvoice = Invoice::query()->findOrFail($credit->fresh()->invoice_id);

    $fromRegular = $this->actingAs($this->admin)
        ->getJson(route('invoices.visits', $regularInvoice))
        ->assertOk()
        ->json('invoices.data');

    $fromCredit = $this->actingAs($this->admin)
        ->getJson(route('invoices.visits', $creditInvoice))
        ->assertOk()
        ->json('invoices.data');

    $regularNumbers = collect($fromRegular)->pluck('invoice_number');
    $creditNumbers = collect($fromCredit)->pluck('invoice_number');

    expect($regularNumbers)->toContain('100801', 'رصيد-1')
        ->and($creditNumbers)->toContain('100801', 'رصيد-1')
        ->and($regularNumbers)->toHaveCount(2)
        ->and($creditNumbers)->toHaveCount(2);
});

it('includes the current invoice when it is completed', function (): void {
    $customer = Customer::factory()->create(['phone_number' => '0599111111']);

    $current = Invoice::factory()->completed()->create([
        'customer_id' => $customer->id,
        'phone_number' => '0599111111',
        'invoice_number' => 'DONE001',
    ]);

    app(InvoiceService::class)->refreshStoredVisitCount('0599111111');

    $this->actingAs($this->admin)
        ->getJson(route('invoices.visits', $current))
        ->assertOk()
        ->assertJsonCount(1, 'invoices.data')
        ->assertJsonPath('invoices.data.0.invoice_number', 'DONE001')
        ->assertJsonPath('visit_count', 1);
});

it('shows previous invoices only for the same phone number', function (): void {
    $customer = Customer::factory()->create(['phone_number' => '0599111111']);

    $current = Invoice::factory()->active()->create([
        'customer_id' => $customer->id,
        'phone_number' => '0599111111',
    ]);

    Invoice::factory()->completed()->create([
        'customer_id' => $customer->id,
        'phone_number' => '0599222222',
        'invoice_number' => '900001',
        'entered_at' => now()->subDay(),
        'exited_at' => now()->subDay()->addHour(),
    ]);

    Invoice::factory()->completed()->create([
        'customer_id' => $customer->id,
        'phone_number' => '0599111111',
        'invoice_number' => '900002',
        'entered_at' => now()->subDays(2),
        'exited_at' => now()->subDays(2)->addHour(),
    ]);

    $this->actingAs($this->admin)
        ->getJson(route('invoices.visits', $current))
        ->assertOk()
        ->assertJsonCount(2, 'invoices.data')
        ->assertJsonPath('invoices.data.0.invoice_number', $current->invoice_number)
        ->assertJsonPath('invoices.data.1.invoice_number', '900002');
});

it('includes expired bonuses in previous visits and counts unique visit days', function (): void {
    Carbon::setTestNow('2026-06-10 12:00:00');

    $customer = Customer::factory()->create(['phone_number' => '0599222333']);
    $sessionDurationId = SessionDuration::query()->value('id');

    $current = Invoice::factory()->active()->create([
        'customer_id' => $customer->id,
        'phone_number' => '0599222333',
        'entered_at' => now(),
    ]);

    $twoDaysAgo = now()->subDays(2);

    Invoice::factory()->completed()->create([
        'customer_id' => $customer->id,
        'phone_number' => '0599222333',
        'entered_at' => $twoDaysAgo->copy()->setTime(11, 0),
        'exited_at' => $twoDaysAgo->copy()->setTime(12, 0),
        'invoice_number' => '100010',
    ]);

    $sourceInvoice = Invoice::factory()->completed()->create([
        'customer_id' => $customer->id,
        'phone_number' => '0599222333',
        'entered_at' => $twoDaysAgo->copy()->setTime(9, 0),
        'exited_at' => $twoDaysAgo->copy()->setTime(10, 0),
    ]);

    $bonus = app(BonusProgramService::class)->addBonus($sourceInvoice, [
        'children_count' => 2,
        'session_duration_id' => $sessionDurationId,
    ], $this->admin);

    $bonus->update([
        'expired_at' => now()->subDays(1),
        'expires_at' => now()->subDays(1),
    ]);

    app(InvoiceService::class)->refreshStoredVisitCount('0599222333');

    $this->actingAs($this->admin)
        ->getJson(route('invoices.visits', $current))
        ->assertOk()
        ->assertJsonPath('visit_count', 3)
        ->assertJsonCount(4, 'invoices.data')
        ->assertJsonPath('invoices.data.1.invoice_number', 'مكافأة منتهية')
        ->assertJsonPath('invoices.data.1.is_expired_bonus', true)
        ->assertJsonPath('invoices.data.1.children_count', 2);
});

it('does not double count expired bonus on same day as invoice visit', function (): void {
    Carbon::setTestNow('2026-06-10 12:00:00');

    $customer = Customer::factory()->create(['phone_number' => '0599333444']);
    $sessionDurationId = SessionDuration::query()->value('id');
    $visitDay = now()->subDay();

    $current = Invoice::factory()->active()->create([
        'customer_id' => $customer->id,
        'phone_number' => '0599333444',
        'entered_at' => now(),
    ]);

    $completedOnDay = Invoice::factory()->completed()->create([
        'customer_id' => $customer->id,
        'phone_number' => '0599333444',
        'entered_at' => $visitDay->copy()->setTime(10, 0),
        'exited_at' => $visitDay->copy()->setTime(11, 0),
        'invoice_number' => '100020',
    ]);

    $sourceInvoice = Invoice::factory()->completed()->create([
        'customer_id' => $customer->id,
        'phone_number' => '0599333444',
        'entered_at' => $visitDay->copy()->setTime(8, 0),
        'exited_at' => $visitDay->copy()->setTime(9, 0),
    ]);

    $bonus = app(BonusProgramService::class)->addBonus($sourceInvoice, [
        'children_count' => 3,
        'session_duration_id' => $sessionDurationId,
    ], $this->admin);

    $bonus->update([
        'expired_at' => $visitDay->copy()->setTime(18, 0),
        'expires_at' => $visitDay->copy()->setTime(18, 0),
    ]);

    app(InvoiceService::class)->refreshStoredVisitCount('0599333444');

    $this->actingAs($this->admin)
        ->getJson(route('invoices.visits', $current))
        ->assertOk()
        ->assertJsonPath('visit_count', 2)
        ->assertJsonCount(4, 'invoices.data')
        ->assertJsonPath('invoices.data.1.invoice_number', 'مكافأة منتهية');
});

it('forbids previous visits for reception users', function (): void {
    $invoice = Invoice::factory()->active()->create();

    $this->actingAs($this->reception)
        ->getJson(route('invoices.visits', $invoice))
        ->assertForbidden();
});

it('adds a pending bonus for admin users from invoice details', function (): void {
    $hour = SessionDuration::query()->where('name', 'ساعة')->first();
    $invoice = Invoice::factory()->active()->create();

    $this->actingAs($this->admin)
        ->postJson(route('invoices.bonuses.store', $invoice), [
            'children_count' => 2,
            'session_duration_id' => $hour->id,
        ])
        ->assertOk()
        ->assertJsonPath('invoice.is_bonus', false)
        ->assertJsonPath('invoice.has_pending_bonus', true);

    expect($invoice->fresh()->is_bonus)->toBeFalse();
    expect($invoice->customer->fresh()->bonuses)->toHaveCount(1)
        ->and($invoice->customer->bonuses->first()->display_number)->toBeNull()
        ->and($invoice->customer->bonuses->first()->source_invoice_id)->toBe($invoice->id)
        ->and($invoice->customer->bonuses->first()->invoice_id)->toBeNull();
});

it('adds a pending bonus from a completed invoice', function (): void {
    $hour = SessionDuration::query()->where('name', 'ساعة')->first();
    $invoice = Invoice::factory()->completed()->create();

    $this->actingAs($this->admin)
        ->postJson(route('invoices.bonuses.store', $invoice), [
            'children_count' => 2,
            'session_duration_id' => $hour->id,
        ])
        ->assertOk()
        ->assertJsonPath('invoice.has_pending_bonus', true);

    expect($invoice->fresh()->status->value)->toBe('completed');
    expect($invoice->customer->fresh()->bonuses)->toHaveCount(1);
});

it('forbids adding bonus for reception users', function (): void {
    $hour = SessionDuration::query()->where('name', 'ساعة')->first();
    $invoice = Invoice::factory()->active()->create();

    $this->actingAs($this->reception)
        ->postJson(route('invoices.bonuses.store', $invoice), [
            'children_count' => 1,
            'session_duration_id' => $hour->id,
        ])
        ->assertForbidden();
});

it('adds bonus even when visit threshold is not reached', function (): void {
    $hour = SessionDuration::query()->where('name', 'ساعة')->first();
    $invoice = Invoice::factory()->active()->create();

    $this->actingAs($this->admin)
        ->postJson(route('invoices.bonuses.store', $invoice), [
            'children_count' => 1,
            'session_duration_id' => $hour->id,
        ])
        ->assertOk()
        ->assertJsonPath('invoice.has_pending_bonus', true);

    expect($invoice->fresh()->is_bonus)->toBeFalse();
});

it('requires authentication for invoice details', function (): void {
    $invoice = Invoice::factory()->active()->create();

    $this->getJson(route('invoices.show', $invoice))->assertUnauthorized();
});

it('allows reception users to checkout active invoices', function (): void {
    $invoice = Invoice::factory()->active()->create();

    $this->actingAs($this->reception)
        ->patch(route('invoices.checkout', $invoice))
        ->assertRedirect();

    expect($invoice->fresh()->status->value)->toBe('completed');
});

it('allows reception users to extend active invoices', function (): void {
    $invoice = Invoice::factory()->active()->create();
    $duration = SessionDuration::query()->first();

    $this->actingAs($this->reception)
        ->post(route('invoices.extend', $invoice), [
            'session_duration_id' => $duration->id,
        ])
        ->assertRedirect();

    expect($invoice->fresh()->extensions)->toHaveCount(1);
});
