<?php

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\SessionDuration;
use App\Models\User;
use App\Services\BonusProgramService;
use App\Services\CreditProgramService;
use Database\Seeders\SessionDurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(SessionDurationSeeder::class);
    $this->admin = User::factory()->admin()->create();
    $this->reception = User::factory()->reception()->create();
    $this->sessionDurationId = SessionDuration::query()->value('id');
});

it('returns pending bonuses and credits for a phone number for admin', function (): void {
    $customer = Customer::factory()->create(['phone_number' => '0599111222']);
    $sourceInvoice = Invoice::factory()->completed()->create([
        'customer_id' => $customer->id,
        'phone_number' => $customer->phone_number,
    ]);

    $pendingBonus = app(BonusProgramService::class)->addBonus($sourceInvoice, [
        'children_count' => 2,
        'session_duration_id' => $this->sessionDurationId,
    ], $this->admin);

    $pendingCredit = app(CreditProgramService::class)->addCredit([
        'phone_number' => $customer->phone_number,
        'children_count' => 3,
        'session_duration_id' => $this->sessionDurationId,
        'counts_as_visit' => true,
    ], $this->admin);

    $this->actingAs($this->admin)
        ->getJson(route('invoices.pending-records', ['phone_number' => '0599111222']))
        ->assertOk()
        ->assertJsonPath('bonuses.0.id', $pendingBonus->id)
        ->assertJsonPath('bonuses.0.phone_number', '0599111222')
        ->assertJsonPath('bonuses.0.children_count', 2)
        ->assertJsonPath('credits.0.id', $pendingCredit->id)
        ->assertJsonPath('credits.0.phone_number', '0599111222')
        ->assertJsonPath('credits.0.children_count', 3);
});

it('returns empty lists when customer has no pending records', function (): void {
    $this->actingAs($this->admin)
        ->getJson(route('invoices.pending-records', ['phone_number' => '0599000000']))
        ->assertOk()
        ->assertJsonPath('bonuses', [])
        ->assertJsonPath('credits', []);
});

it('forbids reception users from looking up pending records', function (): void {
    $this->actingAs($this->reception)
        ->getJson(route('invoices.pending-records', ['phone_number' => '0599111222']))
        ->assertForbidden();
});

it('validates phone number format for pending records lookup', function (): void {
    $this->actingAs($this->admin)
        ->getJson(route('invoices.pending-records', ['phone_number' => '123']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('phone_number');
});

it('returns previous visits for a phone number for admin', function (): void {
    $customer = Customer::factory()->create(['phone_number' => '0599111333']);

    Invoice::factory()->completed()->create([
        'customer_id' => $customer->id,
        'phone_number' => $customer->phone_number,
        'invoice_number' => '101',
    ]);

    $this->actingAs($this->admin)
        ->getJson(route('invoices.previous-visits', ['phone_number' => '0599111333']))
        ->assertOk()
        ->assertJsonPath('phone_number', '0599111333')
        ->assertJsonPath('invoices.data.0.invoice_number', '101');
});

it('forbids reception users from looking up previous visits', function (): void {
    $this->actingAs($this->reception)
        ->getJson(route('invoices.previous-visits', ['phone_number' => '0599111333']))
        ->assertForbidden();
});
