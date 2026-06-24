<?php

use App\Models\Customer;
use App\Models\CustomerBonus;
use App\Models\CustomerCredit;
use App\Models\Invoice;
use App\Models\InvoiceExtension;
use App\Services\LegacyImportService;
use Carbon\Carbon;
use Database\Seeders\SessionDurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\LegacyTestSchema;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(SessionDurationSeeder::class);
    LegacyTestSchema::configureInMemoryConnection();
    LegacyTestSchema::create();
    LegacyTestSchema::seedSampleData();
});

it('resolves legacy timestamps using local created_at when entry_time is offset', function (): void {
    $service = app(LegacyImportService::class);

    $bill = (object) [
        'entry_time' => '2025-07-10 17:36:46',
        'created_at' => '2025-07-10 14:36:46',
        'exit_time' => '2025-07-10 18:42:18',
        'updated_at' => '2025-07-10 15:42:18',
        'status' => 'completed',
    ];

    expect($service->resolveEnteredAt($bill)->toDateTimeString())->toBe('2025-07-10 14:36:46')
        ->and($service->resolveExitedAt($bill)?->toDateTimeString())->toBe('2025-07-10 15:42:18');
});

it('imports legacy entertainment data without duplicating activated bonuses', function (): void {
    Carbon::setTestNow('2026-06-10 20:30:00');

    $stats = app(LegacyImportService::class)->import(fresh: true);

    expect($stats['customers'])->toBe(5)
        ->and($stats['invoices'])->toBe(3)
        ->and($stats['extensions'])->toBe(0)
        ->and($stats['pending_bonuses'])->toBe(1)
        ->and($stats['expired_bonuses'])->toBe(1)
        ->and($stats['skipped_used_rewards'])->toBe(1)
        ->and($stats['credits'])->toBe(1);

    $regularInvoice = Invoice::query()->where('invoice_number', '42')->first();
    expect($regularInvoice)->not->toBeNull()
        ->and($regularInvoice->phone_number)->toBe('0534474054')
        ->and($regularInvoice->is_bonus)->toBeFalse()
        ->and($regularInvoice->is_credit)->toBeFalse();

    $bonusInvoice = Invoice::query()->where('invoice_number', 'مكافأة1')->first();
    expect($bonusInvoice)->not->toBeNull()
        ->and($bonusInvoice->is_bonus)->toBeTrue()
        ->and($bonusInvoice->bonus_display_number)->toBe(1);

    expect(CustomerBonus::query()->where('invoice_id', $bonusInvoice->id)->exists())->toBeFalse();

    $pendingBonus = CustomerBonus::query()->whereHas('customer', fn ($query) => $query->where('phone_number', '0553172518'))->first();
    expect($pendingBonus)->not->toBeNull()
        ->and($pendingBonus->expired_at)->toBeNull()
        ->and($pendingBonus->expires_at?->toDateTimeString())->toBe('2026-07-09 19:14:46')
        ->and($pendingBonus->invoice_id)->toBeNull()
        ->and($pendingBonus->title)->toBe('مكافأة غير مفعلة');

    $expiredBonus = CustomerBonus::query()->whereHas('customer', fn ($query) => $query->where('phone_number', '0508288384'))->first();
    expect($expiredBonus)->not->toBeNull()
        ->and($expiredBonus->expired_at?->toDateTimeString())->toBe('2026-06-02 20:00:00')
        ->and($expiredBonus->expires_at?->toDateTimeString())->toBe('2026-06-02 12:53:59')
        ->and($expiredBonus->title)->toBe('مكافأة منتهية');

    $credit = CustomerCredit::query()->first();
    expect($credit)->not->toBeNull()
        ->and($credit->invoice_id)->toBe(Invoice::query()->where('invoice_number', 'رصيد1')->value('id'));

    $customer = Customer::query()->where('phone_number', '0534474054')->first();
    expect($customer?->visit_count)->toBeGreaterThan(0);
});

it('imports invoice extensions after the first legacy extension row', function (): void {
    $legacy = DB::connection('legacy');

    $legacy->table('bills')->insert([
        'id' => 43,
        'bill_number' => '49',
        'children_count' => 1,
        'phone_number' => '0550045129',
        'entry_time' => '2025-07-09 17:53:47',
        'is_reward' => 0,
        'exit_time' => '2025-07-09 22:35:46',
        'total_duration' => 125,
        'total_price' => 90,
        'status' => 'completed',
        'counts_visit' => 1,
        'created_at' => '2025-07-09 17:53:47',
        'updated_at' => '2025-07-09 19:35:46',
        'original_duration' => 125,
    ]);

    $legacy->table('extensions')->insert([
        ['id' => 59, 'bill_id' => 43, 'play_time_id' => 2, 'duration' => 125, 'price' => 90, 'created_at' => '2025-07-09 17:53:47'],
        ['id' => 60, 'bill_id' => 43, 'play_time_id' => 9, 'duration' => 33, 'price' => 0, 'created_at' => '2025-07-09 18:30:00'],
    ]);

    app(LegacyImportService::class)->import(fresh: true);

    $invoice = Invoice::query()->where('invoice_number', '49')->first();

    expect($invoice)->not->toBeNull()
        ->and(InvoiceExtension::query()->where('invoice_id', $invoice->id)->count())->toBe(1);
});

it('marks legacy bonuses as pending when still within thirty days', function (): void {
    $service = app(LegacyImportService::class);
    $reference = Carbon::parse('2026-06-09 12:00:00');

    $dates = $service->resolveLegacyBonusDates('2026-06-08 19:14:46', $reference);

    expect($dates['expires_at']->toDateTimeString())->toBe('2026-07-09 19:14:46')
        ->and($dates['expired_at'])->toBeNull()
        ->and($dates['title'])->toBe('مكافأة غير مفعلة');
});

it('marks legacy bonuses as expired after thirty days and the daily check time', function (): void {
    $service = app(LegacyImportService::class);
    $reference = Carbon::parse('2026-06-09 20:30:00');

    $dates = $service->resolveLegacyBonusDates('2026-05-02 12:53:59', $reference);

    expect($dates['expires_at']->toDateTimeString())->toBe('2026-06-02 12:53:59')
        ->and($dates['expired_at']->toDateTimeString())->toBe('2026-06-02 20:00:00')
        ->and($dates['title'])->toBe('مكافأة منتهية');
});

it('keeps legacy bonuses pending before the daily check time even when overdue', function (): void {
    $service = app(LegacyImportService::class);
    $reference = Carbon::parse('2026-06-09 12:00:00');

    $dates = $service->resolveLegacyBonusDates('2026-05-02 12:53:59', $reference);

    expect($dates['expires_at']->toDateTimeString())->toBe('2026-06-02 12:53:59')
        ->and($dates['expired_at'])->toBeNull()
        ->and($dates['title'])->toBe('مكافأة غير مفعلة');
});
