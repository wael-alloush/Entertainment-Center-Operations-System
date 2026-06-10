<?php

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceExtension;
use App\Models\SessionDuration;
use App\Models\User;
use Database\Seeders\SessionDurationSeeder;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

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
        'applied_at' => now(),
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

    $this->actingAs($this->admin)
        ->getJson(route('invoices.visits', $current))
        ->assertOk()
        ->assertJsonPath('phone_number', '0599111111')
        ->assertJsonPath('visit_count', 4)
        ->assertJsonPath('average_children', 1.25)
        ->assertJsonCount(5, 'invoices')
        ->assertJsonPath('invoices.0.invoice_number', $current->invoice_number)
        ->assertJsonPath('invoices.1.invoice_number', '100001')
        ->assertJsonPath('invoices.1.children_count', 2)
        ->assertJsonPath('invoices.1.original_duration_minutes', 65)
        ->assertJsonPath('invoices.1.extensions_duration_minutes', 33)
        ->assertJsonPath('invoices.1.total_duration_minutes', 98)
        ->assertJsonPath('invoices.2.invoice_number', '100099')
        ->assertJsonMissingPath('invoices.5');
});

it('includes the current invoice in the visits list even when active', function (): void {
    $current = Invoice::factory()->active()->create([
        'phone_number' => '0599111111',
        'invoice_number' => 'CURRENT1',
        'children_count' => 3,
        'entered_at' => now(),
    ]);

    Invoice::factory()->completed()->create([
        'phone_number' => '0599111111',
        'invoice_number' => 'OLD001',
        'entered_at' => now()->subDay(),
        'exited_at' => now()->subDay()->addHour(),
    ]);

    $this->actingAs($this->admin)
        ->getJson(route('invoices.visits', $current))
        ->assertOk()
        ->assertJsonCount(2, 'invoices')
        ->assertJsonPath('invoices.0.invoice_number', 'CURRENT1')
        ->assertJsonPath('visit_count', 2);
});

it('includes the current invoice when it is completed', function (): void {
    $current = Invoice::factory()->completed()->create([
        'phone_number' => '0599111111',
        'invoice_number' => 'DONE001',
    ]);

    $this->actingAs($this->admin)
        ->getJson(route('invoices.visits', $current))
        ->assertOk()
        ->assertJsonCount(1, 'invoices')
        ->assertJsonPath('invoices.0.invoice_number', 'DONE001')
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
        ->assertJsonCount(2, 'invoices')
        ->assertJsonPath('invoices.0.invoice_number', $current->invoice_number)
        ->assertJsonPath('invoices.1.invoice_number', '900002');
});

it('forbids previous visits for reception users', function (): void {
    $invoice = Invoice::factory()->active()->create();

    $this->actingAs($this->reception)
        ->getJson(route('invoices.visits', $invoice))
        ->assertForbidden();
});

it('stores a bonus for admin users', function (): void {
    $invoice = Invoice::factory()->active()->create();

    $this->actingAs($this->admin)
        ->postJson(route('invoices.bonuses.store', $invoice), [
            'title' => 'جلسة مجانية',
            'notes' => 'مكافأة ولاء',
        ])
        ->assertOk()
        ->assertJsonPath('bonus.title', 'جلسة مجانية');

    expect($invoice->customer->fresh()->bonuses)->toHaveCount(1);
});

it('forbids bonus creation for reception users', function (): void {
    $invoice = Invoice::factory()->active()->create();

    $this->actingAs($this->reception)
        ->postJson(route('invoices.bonuses.store', $invoice), [
            'title' => 'جلسة مجانية',
        ])
        ->assertForbidden();
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
