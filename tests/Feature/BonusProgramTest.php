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
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(SessionDurationSeeder::class);
    $this->admin = User::factory()->admin()->create();
    $this->reception = User::factory()->reception()->create();
});

it('highlights visit count for regular credit and bonus invoices when eligible', function (): void {
    $sessionDurationId = SessionDuration::query()->value('id');
    $customer = Customer::factory()->create([
        'phone_number' => '0599000100',
        'visit_count' => 4,
    ]);

    $loadRelations = ['sessionDuration:id,name,color,duration_minutes', 'customer:id,visit_count'];

    $regular = Invoice::factory()->active()->create([
        'customer_id' => $customer->id,
        'phone_number' => $customer->phone_number,
        'session_duration_id' => $sessionDurationId,
    ])->load($loadRelations);

    $credit = Invoice::factory()->active()->create([
        'customer_id' => $customer->id,
        'phone_number' => $customer->phone_number,
        'session_duration_id' => $sessionDurationId,
        'is_credit' => true,
        'credit_display_number' => 1,
    ])->load($loadRelations);

    $bonus = Invoice::factory()->active()->create([
        'customer_id' => $customer->id,
        'phone_number' => $customer->phone_number,
        'session_duration_id' => $sessionDurationId,
        'is_bonus' => true,
        'bonus_display_number' => 1,
    ])->load($loadRelations);

    $formatter = app(InvoiceService::class);

    expect($formatter->formatInvoice($regular)['is_bonus_eligible'])->toBeTrue()
        ->and($formatter->formatInvoice($credit)['is_bonus_eligible'])->toBeTrue()
        ->and($formatter->formatInvoice($bonus)['is_bonus_eligible'])->toBeTrue();
});

it('marks customers as bonus eligible at each cycle milestone', function (): void {
    $service = app(BonusProgramService::class);

    Setting::setValue('bonus_program', [
        'visit_threshold' => 4,
        'validity_days' => 30,
        'next_display_number' => 1,
    ]);
    Cache::forget('settings.bonus_program');

    expect($service->isEligible(3))->toBeFalse()
        ->and($service->isEligible(4))->toBeTrue()
        ->and($service->isEligible(5))->toBeFalse()
        ->and($service->isEligible(8))->toBeFalse()
        ->and($service->isEligible(9))->toBeTrue()
        ->and($service->isEligible(14))->toBeTrue()
        ->and($service->isEligible(10))->toBeFalse();
});

it('updates visit threshold for admin users', function (): void {
    $this->actingAs($this->admin)
        ->patch(route('management.bonus-program.update'), [
            'visit_threshold' => 6,
            'validity_days' => 30,
            'expiry_check_time' => '20:00',
        ])
        ->assertRedirect();

    expect(app(BonusProgramService::class)->visitThreshold())->toBe(6);
});

it('forbids bonus program updates for reception users', function (): void {
    $this->actingAs($this->reception)
        ->patch(route('management.bonus-program.update'), [
            'visit_threshold' => 6,
            'validity_days' => 30,
            'expiry_check_time' => '20:00',
        ])
        ->assertForbidden();
});

it('blocks duplicate pending bonuses for the same customer', function (): void {
    $sessionDurationId = SessionDuration::query()->value('id');
    $service = app(BonusProgramService::class);
    $customer = Customer::factory()->create(['phone_number' => '0599222333']);
    $sourceInvoice = Invoice::factory()->active()->create([
        'customer_id' => $customer->id,
        'phone_number' => $customer->phone_number,
    ]);

    $service->addBonus($sourceInvoice, [
        'children_count' => 1,
        'session_duration_id' => $sessionDurationId,
    ], $this->admin);

    expect(fn () => $service->addBonus($sourceInvoice, [
        'children_count' => 2,
        'session_duration_id' => $sessionDurationId,
    ], $this->admin))->toThrow(HttpException::class);
});

it('cycles bonus display numbers from 1 to 100', function (): void {
    $sessionDurationId = SessionDuration::query()->value('id');
    $service = app(BonusProgramService::class);

    Setting::setValue('bonus_program', [
        'visit_threshold' => 4,
        'next_display_number' => 100,
    ]);
    Cache::forget('settings.bonus_program');

    $createPendingBonus = function (string $phoneNumber) use ($service, $sessionDurationId): CustomerBonus {
        $customer = Customer::factory()->create(['phone_number' => $phoneNumber]);
        $sourceInvoice = Invoice::factory()->active()->create([
            'customer_id' => $customer->id,
            'phone_number' => $phoneNumber,
        ]);

        return $service->addBonus($sourceInvoice, [
            'children_count' => 1,
            'session_duration_id' => $sessionDurationId,
        ], $this->admin);
    };

    $firstPendingBonus = $createPendingBonus('0599111001');

    $this->actingAs($this->reception)
        ->post(route('customer-bonuses.activate', $firstPendingBonus))
        ->assertRedirect(route('invoices.active'));

    expect(Invoice::query()->find($firstPendingBonus->fresh()->invoice_id)?->bonus_display_number)->toBe(100);

    $secondPendingBonus = $createPendingBonus('0599111002');

    $this->actingAs($this->reception)
        ->post(route('customer-bonuses.activate', $secondPendingBonus))
        ->assertRedirect(route('invoices.active'));

    expect(Invoice::query()->find($secondPendingBonus->fresh()->invoice_id)?->bonus_display_number)->toBe(1);
});
