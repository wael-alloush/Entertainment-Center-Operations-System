<?php

use App\Enums\InvoiceStatus;
use App\Models\Customer;
use App\Models\CustomerBonus;
use App\Models\CustomerCredit;
use App\Models\Invoice;
use App\Models\SessionDuration;
use App\Models\Setting;
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
    $this->sessionDurationId = SessionDuration::query()->value('id');
});

it('allows admin to delete a completed invoice from the record', function (): void {
    $invoice = Invoice::factory()->completed()->create();

    $this->actingAs($this->admin)
        ->delete(route('invoices.completed-record.destroy', $invoice))
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Cancelled);

    $this->actingAs($this->reception)
        ->get(route('invoices.completed'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('invoices.data', 0));
});

it('allows admin to delete a completed credit invoice and its credit record', function (): void {
    $customer = Customer::factory()->create(['phone_number' => '0599111222']);

    $credit = app(CreditProgramService::class)->addCredit([
        'phone_number' => $customer->phone_number,
        'children_count' => 2,
        'session_duration_id' => $this->sessionDurationId,
        'counts_as_visit' => true,
    ], $this->admin);

    $this->actingAs($this->reception)
        ->post(route('customer-credits.activate', $credit));

    $creditInvoice = Invoice::query()->findOrFail($credit->fresh()->invoice_id);
    $creditInvoice->update([
        'status' => InvoiceStatus::Completed,
        'exited_at' => now(),
    ]);

    $this->actingAs($this->admin)
        ->delete(route('invoices.completed-record.destroy', $creditInvoice))
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($creditInvoice->fresh()->status)->toBe(InvoiceStatus::Cancelled)
        ->and(CustomerCredit::query()->whereKey($credit->id)->exists())->toBeFalse();
});

it('allows admin to delete a completed bonus invoice and its bonus record', function (): void {
    $customer = Customer::factory()->create(['phone_number' => '0599333444']);
    $sourceInvoice = Invoice::factory()->completed()->create([
        'customer_id' => $customer->id,
        'phone_number' => $customer->phone_number,
    ]);

    $pendingBonus = app(BonusProgramService::class)->addBonus($sourceInvoice, [
        'children_count' => 2,
        'session_duration_id' => $this->sessionDurationId,
    ], $this->admin);

    $this->actingAs($this->reception)
        ->post(route('customer-bonuses.activate', $pendingBonus));

    $bonusInvoice = Invoice::query()->findOrFail($pendingBonus->fresh()->invoice_id);
    $bonusInvoice->update([
        'status' => InvoiceStatus::Completed,
        'exited_at' => now(),
    ]);

    $this->actingAs($this->admin)
        ->delete(route('invoices.completed-record.destroy', $bonusInvoice))
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($bonusInvoice->fresh()->status)->toBe(InvoiceStatus::Cancelled)
        ->and(CustomerBonus::query()->whereKey($pendingBonus->id)->exists())->toBeFalse();
});

it('allows admin to delete an expired bonus from the completed list', function (): void {
    Carbon::setTestNow('2026-06-01 10:00:00');

    Setting::setValue('bonus_program', [
        'visit_threshold' => 4,
        'validity_days' => 30,
        'next_display_number' => 1,
    ]);

    $customer = Customer::factory()->create(['phone_number' => '0599000111']);
    $sourceInvoice = Invoice::factory()->completed()->create([
        'customer_id' => $customer->id,
        'phone_number' => $customer->phone_number,
    ]);

    $bonus = app(BonusProgramService::class)->addBonus($sourceInvoice, [
        'children_count' => 3,
        'session_duration_id' => $this->sessionDurationId,
    ], $this->admin);

    Carbon::setTestNow('2026-07-02 20:30:00');
    app(BonusProgramService::class)->expireOverdueBonuses();
    $bonus->refresh();

    $this->actingAs($this->admin)
        ->delete(route('customer-bonuses.completed-record.destroy', $bonus))
        ->assertRedirect()
        ->assertSessionHas('success');

    expect(CustomerBonus::query()->whereKey($bonus->id)->exists())->toBeFalse();

    $this->actingAs($this->admin)
        ->get(route('invoices.completed', ['from_date' => '', 'to_date' => '']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('invoices.data', 1));
});

it('forbids reception users from deleting completed records', function (): void {
    $invoice = Invoice::factory()->completed()->create();

    $this->actingAs($this->reception)
        ->delete(route('invoices.completed-record.destroy', $invoice))
        ->assertForbidden();

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Completed);
});

it('forbids deleting active invoices from completed records', function (): void {
    $invoice = Invoice::factory()->active()->create();

    $this->actingAs($this->admin)
        ->delete(route('invoices.completed-record.destroy', $invoice))
        ->assertStatus(422);

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Active);
});

it('excludes cancelled invoices from customer visit count', function (): void {
    $customer = Customer::factory()->create([
        'phone_number' => '0598000777',
    ]);

    $invoice = Invoice::factory()->completed()->create([
        'customer_id' => $customer->id,
        'phone_number' => $customer->phone_number,
        'entered_at' => now(),
    ]);

    app(InvoiceService::class)->refreshStoredVisitCount($customer->phone_number);

    expect($customer->fresh()->visit_count)->toBe(1);

    $this->actingAs($this->admin)
        ->delete(route('invoices.completed-record.destroy', $invoice))
        ->assertRedirect();

    expect($customer->fresh()->visit_count)->toBe(0);
});
