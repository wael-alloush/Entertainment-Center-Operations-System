<?php

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\SessionDuration;
use App\Models\Setting;
use App\Models\User;
use App\Services\BonusProgramService;
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

it('sets expiry date when adding a pending bonus', function (): void {
    Carbon::setTestNow('2026-06-01 10:00:00');

    Setting::setValue('bonus_program', [
        'visit_threshold' => 4,
        'validity_days' => 30,
        'next_display_number' => 1,
    ]);

    $invoice = Invoice::factory()->completed()->create();
    $bonus = app(BonusProgramService::class)->addBonus($invoice, [
        'children_count' => 2,
        'session_duration_id' => $this->sessionDurationId,
    ], $this->admin);

    expect($bonus->expires_at?->toDateTimeString())->toBe('2026-07-02 10:00:00')
        ->and($bonus->expired_at)->toBeNull()
        ->and(app(BonusProgramService::class)->validityDays())->toBe(30)
        ->and(app(BonusProgramService::class)->effectiveValidityDays())->toBe(31)
        ->and(app(BonusProgramService::class)->expiryCheckTime())->toBe('20:00:00');
});

it('expires overdue pending bonuses and shows them in completed invoices', function (): void {
    Carbon::setTestNow('2026-06-01 10:00:00');

    $customer = Customer::factory()->create(['phone_number' => '0599000111']);
    $sourceInvoice = Invoice::factory()->completed()->create([
        'customer_id' => $customer->id,
        'phone_number' => $customer->phone_number,
    ]);

    $bonus = app(BonusProgramService::class)->addBonus($sourceInvoice, [
        'children_count' => 3,
        'session_duration_id' => $this->sessionDurationId,
    ], $this->admin);

    Carbon::setTestNow('2026-07-03 20:30:00');

    expect(app(BonusProgramService::class)->expireOverdueBonuses())->toBe(1);

    $bonus->refresh();
    expect($bonus->expired_at?->toDateTimeString())->toBe('2026-07-02 20:00:00');

    Carbon::setTestNow('2026-07-02 20:30:00');

    $this->actingAs($this->reception)
        ->get(route('invoices.completed'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('invoices/completed')
            ->has('invoices.data', 1)
            ->where('invoices.data.0.is_expired_bonus', true)
            ->where('invoices.data.0.invoice_display_label', 'مكافأة منتهية')
            ->where('invoices.data.0.phone_number', '0599000111')
            ->where('invoices.data.0.expired_at', '08:00 م'));
});

it('returns expired bonus details as json', function (): void {
    Carbon::setTestNow('2026-06-01 10:00:00');

    $customer = Customer::factory()->create(['phone_number' => '0599888777']);
    $invoice = Invoice::factory()->completed()->create([
        'customer_id' => $customer->id,
        'phone_number' => $customer->phone_number,
    ]);
    $bonus = app(BonusProgramService::class)->addBonus($invoice, [
        'children_count' => 2,
        'session_duration_id' => $this->sessionDurationId,
    ], $this->admin);

    Carbon::setTestNow('2026-08-01 20:30:00');
    app(BonusProgramService::class)->expireOverdueBonuses();
    $bonus->refresh();

    $this->actingAs($this->reception)
        ->getJson(route('customer-bonuses.show', $bonus))
        ->assertOk()
        ->assertJsonPath('invoice.is_expired_bonus', true)
        ->assertJsonPath('invoice.invoice_display_label', 'مكافأة منتهية')
        ->assertJsonPath('invoice.status_label', 'مكافأة منتهية')
        ->assertJsonPath('invoice.children_count', 2)
        ->assertJsonPath('invoice.phone_number', '0599888777');
});

it('returns previous visits for an expired bonus', function (): void {
    Carbon::setTestNow('2026-06-01 10:00:00');

    $customer = Customer::factory()->create(['phone_number' => '0599111222']);
    $sourceInvoice = Invoice::factory()->completed()->create([
        'customer_id' => $customer->id,
        'phone_number' => '0599111222',
    ]);

    $bonus = app(BonusProgramService::class)->addBonus($sourceInvoice, [
        'children_count' => 1,
        'session_duration_id' => $this->sessionDurationId,
    ], $this->admin);

    Carbon::setTestNow('2026-08-01 20:30:00');
    app(BonusProgramService::class)->expireOverdueBonuses();
    $bonus->refresh();

    $this->actingAs($this->admin)
        ->getJson(route('customer-bonuses.visits', $bonus))
        ->assertOk()
        ->assertJsonPath('phone_number', '0599111222')
        ->assertJsonPath('invoices.data.0.is_expired_bonus', true);
});

it('forbids showing pending bonus details', function (): void {
    $invoice = Invoice::factory()->completed()->create();
    $bonus = app(BonusProgramService::class)->addBonus($invoice, [
        'children_count' => 1,
        'session_duration_id' => $this->sessionDurationId,
    ], $this->admin);

    $this->actingAs($this->reception)
        ->getJson(route('customer-bonuses.show', $bonus))
        ->assertNotFound();
});

it('forbids activating an expired bonus', function (): void {
    Carbon::setTestNow('2026-06-01 10:00:00');

    $invoice = Invoice::factory()->completed()->create();
    $bonus = app(BonusProgramService::class)->addBonus($invoice, [
        'children_count' => 1,
        'session_duration_id' => $this->sessionDurationId,
    ], $this->admin);

    Carbon::setTestNow('2026-08-01 20:30:00');
    app(BonusProgramService::class)->expireOverdueBonuses();

    $this->actingAs($this->reception)
        ->post(route('customer-bonuses.activate', $bonus))
        ->assertStatus(422);
});

it('keeps bonuses activatable after expires_at until the next daily check time', function (): void {
    Carbon::setTestNow('2026-06-01 10:00:00');

    Setting::setValue('bonus_program', [
        'visit_threshold' => 4,
        'validity_days' => 30,
        'expiry_check_time' => '12:00',
        'next_display_number' => 1,
    ]);

    $customer = Customer::factory()->create();
    $sourceInvoice = Invoice::factory()->completed()->create([
        'customer_id' => $customer->id,
        'phone_number' => $customer->phone_number,
    ]);

    $bonus = app(BonusProgramService::class)->addBonus($sourceInvoice, [
        'children_count' => 1,
        'session_duration_id' => $this->sessionDurationId,
    ], $this->admin);

    $bonus->update(['expires_at' => '2026-06-24 19:00:00']);

    Carbon::setTestNow('2026-06-24 19:30:00');

    expect(app(BonusProgramService::class)->shouldFinalizeBonus($bonus))->toBeFalse()
        ->and(app(BonusProgramService::class)->isBonusExpired($bonus))->toBeFalse()
        ->and(app(BonusProgramService::class)->expireOverdueBonuses())->toBe(0)
        ->and($bonus->fresh()->expired_at)->toBeNull();

    $this->actingAs($this->reception)
        ->post(route('customer-bonuses.activate', $bonus))
        ->assertRedirect(route('invoices.active'));

    $unusedCustomer = Customer::factory()->create();
    $unusedSourceInvoice = Invoice::factory()->completed()->create([
        'customer_id' => $unusedCustomer->id,
        'phone_number' => $unusedCustomer->phone_number,
    ]);

    Carbon::setTestNow('2026-06-01 10:00:00');

    $unusedBonus = app(BonusProgramService::class)->addBonus($unusedSourceInvoice, [
        'children_count' => 1,
        'session_duration_id' => $this->sessionDurationId,
    ], $this->admin);

    $unusedBonus->update(['expires_at' => '2026-06-24 19:00:00']);

    Carbon::setTestNow('2026-06-25 11:30:00');

    expect(app(BonusProgramService::class)->expireOverdueBonuses())->toBe(0)
        ->and($unusedBonus->fresh()->expired_at)->toBeNull();

    Carbon::setTestNow('2026-06-25 12:30:00');

    expect(app(BonusProgramService::class)->expireOverdueBonuses())->toBe(1)
        ->and($unusedBonus->fresh()->expired_at?->toDateTimeString())->toBe('2026-06-25 12:00:00');
});

it('still finalizes bonuses later the same day after an earlier expiry check visit', function (): void {
    Carbon::setTestNow('2026-06-01 10:00:00');

    Setting::setValue('bonus_program', [
        'visit_threshold' => 4,
        'validity_days' => 30,
        'expiry_check_time' => '20:00',
        'next_display_number' => 1,
    ]);
    Cache::forget('settings.bonus_program');

    $invoice = Invoice::factory()->completed()->create();
    $bonus = app(BonusProgramService::class)->addBonus($invoice, [
        'children_count' => 1,
        'session_duration_id' => $this->sessionDurationId,
    ], $this->admin);

    $bonus->update(['expires_at' => '2026-07-02 10:00:00']);

    Carbon::setTestNow('2026-07-02 12:00:00');

    expect(app(BonusProgramService::class)->expireOverdueBonusesIfNeeded())->toBe(0)
        ->and($bonus->fresh()->expired_at)->toBeNull();

    Carbon::setTestNow('2026-07-02 20:15:00');

    expect(app(BonusProgramService::class)->expireOverdueBonusesIfNeeded())->toBe(1)
        ->and($bonus->fresh()->expired_at?->toDateTimeString())->toBe('2026-07-02 20:00:00');
});

it('does not finalize overdue bonuses before the daily check time on later days', function (): void {
    Carbon::setTestNow('2026-06-01 10:00:00');

    $invoice = Invoice::factory()->completed()->create();
    $bonus = app(BonusProgramService::class)->addBonus($invoice, [
        'children_count' => 1,
        'session_duration_id' => $this->sessionDurationId,
    ], $this->admin);

    Carbon::setTestNow('2026-07-03 12:00:00');

    expect(app(BonusProgramService::class)->expireOverdueBonuses())->toBe(0)
        ->and($bonus->fresh()->expired_at)->toBeNull();

    Carbon::setTestNow('2026-07-03 20:30:00');

    expect(app(BonusProgramService::class)->expireOverdueBonuses())->toBe(1)
        ->and($bonus->fresh()->expired_at?->toDateTimeString())->toBe('2026-07-02 20:00:00');
});

it('does not expire bonuses before the configured daily check time', function (): void {
    Carbon::setTestNow('2026-06-01 10:00:00');

    Setting::setValue('bonus_program', [
        'visit_threshold' => 4,
        'validity_days' => 30,
        'expiry_check_time' => '20:00',
        'next_display_number' => 1,
    ]);

    $invoice = Invoice::factory()->completed()->create();
    $bonus = app(BonusProgramService::class)->addBonus($invoice, [
        'children_count' => 1,
        'session_duration_id' => $this->sessionDurationId,
    ], $this->admin);

    Carbon::setTestNow('2026-07-02 19:30:00');

    expect(app(BonusProgramService::class)->expireOverdueBonuses())->toBe(0)
        ->and($bonus->fresh()->expired_at)->toBeNull();

    Carbon::setTestNow('2026-07-02 20:15:00');

    expect(app(BonusProgramService::class)->expireOverdueBonuses())->toBe(1)
        ->and($bonus->fresh()->expired_at?->toDateTimeString())->toBe('2026-07-02 20:00:00');
});

it('updates validity days for admin users', function (): void {
    $this->actingAs($this->admin)
        ->patch(route('management.bonus-program.update'), [
            'visit_threshold' => 4,
            'validity_days' => 45,
            'expiry_check_time' => '21:30',
        ])
        ->assertRedirect();

    expect(app(BonusProgramService::class)->validityDays())->toBe(45)
        ->and(app(BonusProgramService::class)->expiryCheckTime())->toBe('21:30:00');
});

it('removes expired bonuses from pending list', function (): void {
    Carbon::setTestNow('2026-06-01 10:00:00');

    $invoice = Invoice::factory()->completed()->create();
    app(BonusProgramService::class)->addBonus($invoice, [
        'children_count' => 1,
        'session_duration_id' => $this->sessionDurationId,
    ], $this->admin);

    Carbon::setTestNow('2026-08-01 20:30:00');

    $this->actingAs($this->reception)
        ->get(route('invoices.bonuses'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('invoices/bonuses')
            ->where('bonuses.data', [])
            ->where('pendingBonusCount', 0));
});
