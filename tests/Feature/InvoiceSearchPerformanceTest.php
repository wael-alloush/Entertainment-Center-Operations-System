<?php

use App\Models\Customer;
use App\Models\CustomerBonus;
use App\Models\Invoice;
use App\Models\SessionDuration;
use App\Models\Setting;
use App\Models\User;
use App\Services\BonusProgramService;
use App\Services\InvoiceService;
use Database\Seeders\SessionDurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(SessionDurationSeeder::class);
    $this->admin = User::factory()->admin()->create();
    $this->sessionDurationId = (int) SessionDuration::query()->value('id');
});

it('paginates completed rows with database union instead of loading all rows', function (): void {
    Carbon::setTestNow('2026-06-10 14:00:00');

    Invoice::factory()->completed()->count(30)->create([
        'entered_at' => now()->subDay(),
        'exited_at' => now()->subDay(),
    ]);

    $request = Request::create(route('invoices.completed', ['from_date' => '', 'to_date' => '']), 'GET');
    $request->setUserResolver(fn (): User => $this->admin);

    $paginator = app(InvoiceService::class)->completedRowsList($request);

    expect($paginator->total())->toBe(30)
        ->and($paginator->count())->toBe(25)
        ->and($paginator->currentPage())->toBe(1);
});

it('merges expired bonuses into completed rows using union pagination', function (): void {
    Carbon::setTestNow('2026-06-01 10:00:00');

    $customer = Customer::factory()->create(['phone_number' => '0599000111']);
    $sourceInvoice = Invoice::factory()->completed()->create([
        'customer_id' => $customer->id,
        'phone_number' => $customer->phone_number,
        'entered_at' => now()->subDays(2),
        'exited_at' => now()->subDays(2),
        'invoice_number' => '500001',
    ]);

    $bonus = app(BonusProgramService::class)->addBonus($sourceInvoice, [
        'children_count' => 2,
        'session_duration_id' => $this->sessionDurationId,
    ], $this->admin);

    Carbon::setTestNow('2026-08-01 20:30:00');
    app(BonusProgramService::class)->expireOverdueBonuses();
    $bonus->refresh();

    Invoice::factory()->completed()->create([
        'entered_at' => Carbon::parse('2026-06-01 09:00:00'),
        'exited_at' => Carbon::parse('2026-06-01 09:00:00'),
        'invoice_number' => '500002',
    ]);

    $request = Request::create(route('invoices.completed', [
        'from_date' => '2026-06-01',
        'to_date' => '2026-08-01',
    ]), 'GET');
    $request->setUserResolver(fn (): User => $this->admin);

    $paginator = app(InvoiceService::class)->completedRowsList($request);
    $rows = collect($paginator->items());

    expect($paginator->total())->toBe(2)
        ->and($rows->contains(fn (array $row): bool => ($row['is_expired_bonus'] ?? false) === true))->toBeTrue()
        ->and($rows->contains(fn (array $row): bool => ($row['invoice_number'] ?? null) === '500002'))->toBeTrue();
});

it('finds pending bonuses by phone prefix without scanning all customers', function (): void {
    $matchingCustomer = Customer::factory()->create(['phone_number' => '0599111222']);
    $otherCustomer = Customer::factory()->create(['phone_number' => '0599333444']);

    CustomerBonus::query()->create([
        'customer_id' => $matchingCustomer->id,
        'children_count' => 1,
        'session_duration_id' => $this->sessionDurationId,
        'title' => 'مكافأة غير مفعلة',
        'expires_at' => now()->addDays(30),
    ]);

    CustomerBonus::query()->create([
        'customer_id' => $otherCustomer->id,
        'children_count' => 1,
        'session_duration_id' => $this->sessionDurationId,
        'title' => 'مكافأة غير مفعلة',
        'expires_at' => now()->addDays(30),
    ]);

    $request = Request::create(route('invoices.bonuses', ['search' => '0599111']), 'GET');

    $paginator = app(InvoiceService::class)->pendingBonusInvoices($request);

    expect($paginator->total())->toBe(1)
        ->and($paginator->items()[0]['phone_number'])->toBe('0599111222');
});

it('runs bonus expiry check once per day after the configured time', function (): void {
    Setting::setValue('bonus_program', [
        'visit_threshold' => 4,
        'validity_days' => 30,
        'expiry_check_time' => '20:00',
        'next_display_number' => 1,
    ]);
    Cache::forget('settings.bonus_program');

    Carbon::setTestNow('2026-06-10 19:30:00');

    $customer = Customer::factory()->create(['phone_number' => '0599777888']);
    $sourceInvoice = Invoice::factory()->completed()->create([
        'customer_id' => $customer->id,
        'phone_number' => $customer->phone_number,
    ]);

    CustomerBonus::query()->create([
        'customer_id' => $customer->id,
        'source_invoice_id' => $sourceInvoice->id,
        'children_count' => 1,
        'session_duration_id' => $this->sessionDurationId,
        'title' => 'مكافأة غير مفعلة',
        'expires_at' => now()->subDay(),
        'expired_at' => null,
        'display_number' => null,
        'invoice_id' => null,
    ]);

    $bonusProgram = app(BonusProgramService::class);

    expect($bonusProgram->expireOverdueBonusesIfNeeded())->toBe(0);

    Carbon::setTestNow('2026-06-10 20:01:00');

    expect($bonusProgram->expireOverdueBonusesIfNeeded())->toBe(1)
        ->and($bonusProgram->expireOverdueBonusesIfNeeded())->toBe(0);

    Carbon::setTestNow('2026-06-10 21:00:00');

    expect($bonusProgram->expireOverdueBonusesIfNeeded())->toBe(0);
});

it('paginates previous visits instead of truncating them', function (): void {
    $customer = Customer::factory()->create(['phone_number' => '0599666555']);

    Invoice::factory()->completed()->count(35)->create([
        'customer_id' => $customer->id,
        'phone_number' => $customer->phone_number,
    ]);

    $firstPage = app(InvoiceService::class)->formatPreviousVisitsForPhone($customer->phone_number);
    $secondPageRequest = Request::create('/', 'GET', ['page' => 2]);
    $secondPage = app(InvoiceService::class)->formatPreviousVisitsForPhone($customer->phone_number, $secondPageRequest);

    expect($firstPage['invoices']['total'])->toBe(35)
        ->and($firstPage['invoices']['data'])->toHaveCount(InvoiceService::PREVIOUS_VISITS_PER_PAGE)
        ->and($firstPage['invoices']['last_page'])->toBe(3)
        ->and($secondPage['invoices']['current_page'])->toBe(2)
        ->and($secondPage['invoices']['data'])->toHaveCount(InvoiceService::PREVIOUS_VISITS_PER_PAGE);
});

it('preloads pending bonus flags for invoice lists in one query', function (): void {
    $customer = Customer::factory()->create(['phone_number' => '0599555666']);

    CustomerBonus::query()->create([
        'customer_id' => $customer->id,
        'children_count' => 1,
        'session_duration_id' => $this->sessionDurationId,
        'title' => 'مكافأة غير مفعلة',
        'expires_at' => now()->addDays(30),
    ]);

    Invoice::factory()->active()->create([
        'customer_id' => $customer->id,
        'phone_number' => $customer->phone_number,
    ]);

    $bonusProgram = app(BonusProgramService::class);
    $bonusProgram->preloadPendingBonusForCustomers([$customer->id]);

    DB::enableQueryLog();
    DB::flushQueryLog();

    expect($bonusProgram->hasPendingBonusForCustomer($customer->id))->toBeTrue();

    expect(count(DB::getQueryLog()))->toBe(0);
});
