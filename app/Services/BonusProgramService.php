<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Models\CustomerBonus;
use App\Models\Invoice;
use App\Models\SessionDuration;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class BonusProgramService
{
    /** @var array<int, bool> */
    private array $pendingBonusCache = [];

    public function __construct(private InvoiceService $invoiceService) {}

    public const SETTING_KEY = 'bonus_program';

    public const DEFAULT_EXPIRY_CHECK_TIME = '20:00';

    public const MAX_DISPLAY_NUMBER = 100;

    public const VALIDITY_GRACE_DAYS = 1;

    /**
     * @return array{visit_threshold: int, validity_days: int, expiry_check_time: string, next_display_number: int}
     */
    public function settings(): array
    {
        $settings = Cache::remember(
            'settings.bonus_program',
            now()->addHour(),
            fn (): array => Setting::getValue(self::SETTING_KEY, [
                'visit_threshold' => 4,
                'validity_days' => 30,
                'expiry_check_time' => self::DEFAULT_EXPIRY_CHECK_TIME,
                'next_display_number' => 1,
            ]) ?? [
                'visit_threshold' => 4,
                'validity_days' => 30,
                'expiry_check_time' => self::DEFAULT_EXPIRY_CHECK_TIME,
                'next_display_number' => 1,
            ],
        );

        return [
            'visit_threshold' => max(1, (int) ($settings['visit_threshold'] ?? 4)),
            'validity_days' => max(1, (int) ($settings['validity_days'] ?? 30)),
            'expiry_check_time' => $this->formatExpiryCheckTimeForDisplay(
                (string) ($settings['expiry_check_time'] ?? self::DEFAULT_EXPIRY_CHECK_TIME),
            ),
            'next_display_number' => $this->normalizeDisplayNumber((int) ($settings['next_display_number'] ?? 1)),
        ];
    }

    /**
     * @param  array{visit_threshold: int, validity_days?: int, expiry_check_time?: string}  $data
     */
    public function updateSettings(array $data): void
    {
        $current = $this->settings();

        Setting::setValue(self::SETTING_KEY, [
            'visit_threshold' => max(1, (int) $data['visit_threshold']),
            'validity_days' => max(1, (int) ($data['validity_days'] ?? $current['validity_days'])),
            'expiry_check_time' => $this->normalizeExpiryCheckTime(
                (string) ($data['expiry_check_time'] ?? $current['expiry_check_time']),
            ),
            'next_display_number' => $current['next_display_number'],
        ]);

        Cache::forget('settings.bonus_program');
    }

    public function visitThreshold(): int
    {
        return $this->settings()['visit_threshold'];
    }

    public function validityDays(): int
    {
        return $this->settings()['validity_days'];
    }

    public function expiryCheckTime(): string
    {
        return $this->normalizeExpiryCheckTime($this->settings()['expiry_check_time']);
    }

    public function expiryCheckTimeForSchedule(): string
    {
        return substr($this->expiryCheckTime(), 0, 5);
    }

    public function effectiveValidityDays(): int
    {
        return $this->validityDays() + self::VALIDITY_GRACE_DAYS;
    }

    public function calculateExpiresAt(?Carbon $from = null): Carbon
    {
        $from ??= now();

        return $from->copy()->addDays($this->effectiveValidityDays());
    }

    public function scheduledFinalizationAt(CustomerBonus $bonus): ?Carbon
    {
        if ($bonus->expires_at === null) {
            return null;
        }

        $expiresAt = $bonus->expires_at->copy();
        $finalizationAt = $this->expiryCheckAtForDate($expiresAt);

        if ($finalizationAt->lt($expiresAt)) {
            $finalizationAt = $this->expiryCheckAtForDate($expiresAt->copy()->addDay());
        }

        return $finalizationAt;
    }

    public function shouldFinalizeBonus(CustomerBonus $bonus, ?Carbon $at = null): bool
    {
        if ($bonus->expired_at !== null || $bonus->expires_at === null) {
            return false;
        }

        $finalizationAt = $this->scheduledFinalizationAt($bonus);

        return $finalizationAt !== null && ($at ?? now())->gte($finalizationAt);
    }

    public function isBonusExpired(CustomerBonus $bonus, ?Carbon $at = null): bool
    {
        if ($bonus->expired_at !== null) {
            return true;
        }

        return $this->shouldFinalizeBonus($bonus, $at);
    }

    public function isEligible(int $visitCount, ?int $threshold = null): bool
    {
        $threshold ??= $this->visitThreshold();

        if ($visitCount < $threshold) {
            return false;
        }

        return $visitCount % ($threshold + 1) === $threshold;
    }

    public function expireOverdueBonusesIfNeeded(): int
    {
        if (! $this->hasPassedDailyExpiryCheckTime()) {
            return 0;
        }

        if (
            Cache::has($this->dailyExpiryCheckCacheKey())
            && ! $this->hasPendingBonusesEligibleForFinalization()
            && ! $this->hasPendingBonusesWithFinalizationLaterToday()
        ) {
            return 0;
        }

        $expiredCount = $this->expireOverdueBonuses();

        if (
            ! $this->hasPendingBonusesEligibleForFinalization()
            && ! $this->hasPendingBonusesWithFinalizationLaterToday()
        ) {
            Cache::put($this->dailyExpiryCheckCacheKey(), true, now()->endOfDay());
        }

        return $expiredCount;
    }

    public function expireOverdueBonuses(): int
    {
        if (! $this->hasPassedDailyExpiryCheckTime()) {
            return 0;
        }

        $bonuses = CustomerBonus::query()
            ->pending()
            ->whereNull('expired_at')
            ->whereNotNull('expires_at')
            ->get()
            ->filter(fn (CustomerBonus $bonus): bool => $this->shouldFinalizeBonus($bonus));

        foreach ($bonuses as $bonus) {
            $bonus->update([
                'expired_at' => $this->scheduledFinalizationAt($bonus),
                'title' => 'مكافأة منتهية',
            ]);
        }

        $bonuses
            ->load('customer:id,phone_number')
            ->pluck('customer.phone_number')
            ->unique()
            ->filter()
            ->each(fn (string $phoneNumber) => $this->invoiceService->refreshStoredVisitCount($phoneNumber));

        return $bonuses->count();
    }

    public function addBonus(Invoice $sourceInvoice, array $data, ?User $user = null): CustomerBonus
    {
        abort_if($sourceInvoice->customer_id === null, 422, 'لا يوجد عميل مرتبط بهذه الفاتورة.');
        abort_if($this->hasPendingBonusForCustomer($sourceInvoice->customer_id), 422, 'توجد مكافأة معلقة لهذا العميل مسبقاً.');

        $sessionDuration = SessionDuration::query()->findOrFail($data['session_duration_id']);
        $expiresAt = $this->calculateExpiresAt();

        $bonus = CustomerBonus::query()->create([
            'customer_id' => $sourceInvoice->customer_id,
            'source_invoice_id' => $sourceInvoice->id,
            'invoice_id' => null,
            'children_count' => $data['children_count'],
            'session_duration_id' => $sessionDuration->id,
            'display_number' => null,
            'expires_at' => $expiresAt,
            'expired_at' => null,
            'title' => 'مكافأة غير مفعلة',
            'created_by' => $user?->id,
        ]);

        $this->pendingBonusCache[$sourceInvoice->customer_id] = true;

        return $bonus;
    }

    public function activateBonus(CustomerBonus $pendingBonus, ?User $user = null): Invoice
    {
        abort_if($pendingBonus->display_number !== null || $pendingBonus->invoice_id !== null, 422, 'تم تفعيل هذه المكافأة مسبقاً.');
        abort_if($this->isBonusExpired($pendingBonus), 422, 'انتهت صلاحية هذه المكافأة.');

        return DB::transaction(function () use ($pendingBonus, $user): Invoice {
            $pendingBonus = CustomerBonus::query()->whereKey($pendingBonus->id)->lockForUpdate()->firstOrFail();
            $customer = $pendingBonus->customer()->lockForUpdate()->firstOrFail();
            $sessionDuration = SessionDuration::query()->findOrFail($pendingBonus->session_duration_id);
            $displayNumber = $this->reserveNextDisplayNumber();
            $enteredAt = now();

            $bonusInvoice = Invoice::query()->create([
                'invoice_number' => 'مكافأة-'.$displayNumber,
                'customer_id' => $customer->id,
                'phone_number' => $customer->phone_number,
                'children_count' => $pendingBonus->children_count,
                'session_duration_id' => $sessionDuration->id,
                'status' => InvoiceStatus::Active,
                'is_bonus' => true,
                'bonus_display_number' => $displayNumber,
                'entered_at' => $enteredAt,
                'expected_exit_at' => $this->invoiceService->calculateExpectedExitAt($enteredAt, $sessionDuration),
                'created_by' => $user?->id,
            ]);

            $pendingBonus->update([
                'invoice_id' => $bonusInvoice->id,
                'display_number' => $displayNumber,
                'title' => 'مكافأة '.$displayNumber,
            ]);

            $this->pendingBonusCache[$customer->id] = false;

            $this->invoiceService->refreshStoredVisitCount($customer->phone_number);

            return $bonusInvoice->fresh(['sessionDuration', 'extensions.sessionDuration', 'customer']);
        });
    }

    /**
     * @param  array<int, int|null>  $customerIds
     */
    public function preloadPendingBonusForCustomers(array $customerIds): void
    {
        $uncachedCustomerIds = array_values(array_filter(
            array_unique(array_map('intval', $customerIds)),
            fn (int $customerId): bool => $customerId > 0
                && ! array_key_exists($customerId, $this->pendingBonusCache),
        ));

        if ($uncachedCustomerIds === []) {
            return;
        }

        $customerIdsWithPendingBonus = CustomerBonus::query()
            ->pending()
            ->whereIn('customer_id', $uncachedCustomerIds)
            ->distinct()
            ->pluck('customer_id');

        foreach ($uncachedCustomerIds as $customerId) {
            $this->pendingBonusCache[$customerId] = $customerIdsWithPendingBonus->contains($customerId);
        }
    }

    public function hasPendingBonusForCustomer(int $customerId): bool
    {
        if (array_key_exists($customerId, $this->pendingBonusCache)) {
            return $this->pendingBonusCache[$customerId];
        }

        $this->preloadPendingBonusForCustomers([$customerId]);

        return $this->pendingBonusCache[$customerId] ?? false;
    }

    public function hasPassedDailyExpiryCheckTime(?Carbon $at = null): bool
    {
        $at ??= now();

        return $at->gte($this->expiryCheckAtForDate($at));
    }

    private function expiryCheckAtForDate(Carbon $date): Carbon
    {
        [$hour, $minute] = array_map('intval', explode(':', substr($this->expiryCheckTime(), 0, 5)));

        return $date->copy()->setTime($hour, $minute, 0);
    }

    private function hasPendingBonusesEligibleForFinalization(): bool
    {
        return CustomerBonus::query()
            ->pending()
            ->whereNotNull('expires_at')
            ->get()
            ->contains(fn (CustomerBonus $bonus): bool => $this->shouldFinalizeBonus($bonus));
    }

    private function hasPendingBonusesWithFinalizationLaterToday(): bool
    {
        $today = now()->toDateString();

        return CustomerBonus::query()
            ->pending()
            ->whereNotNull('expires_at')
            ->get()
            ->contains(function (CustomerBonus $bonus) use ($today): bool {
                $finalizationAt = $this->scheduledFinalizationAt($bonus);

                return $finalizationAt !== null
                    && $finalizationAt->toDateString() === $today
                    && now()->lt($finalizationAt);
            });
    }

    private function dailyExpiryCheckCacheKey(): string
    {
        return 'bonuses.expiry_checked.'.now()->toDateString();
    }

    private function normalizeExpiryCheckTime(string $time): string
    {
        $time = trim($time);

        if (strlen($time) === 5) {
            return "{$time}:00";
        }

        return $time;
    }

    private function formatExpiryCheckTimeForDisplay(string $time): string
    {
        return substr($this->normalizeExpiryCheckTime($time), 0, 5);
    }

    private function reserveNextDisplayNumber(): int
    {
        return DB::transaction(function (): int {
            $setting = Setting::query()
                ->where('key', self::SETTING_KEY)
                ->lockForUpdate()
                ->first();

            $value = is_array($setting?->value) ? $setting->value : [];
            $number = $this->normalizeDisplayNumber((int) ($value['next_display_number'] ?? 1));
            $nextNumber = $number >= self::MAX_DISPLAY_NUMBER ? 1 : $number + 1;

            Setting::query()->updateOrCreate(
                ['key' => self::SETTING_KEY],
                ['value' => [
                    'visit_threshold' => max(1, (int) ($value['visit_threshold'] ?? 4)),
                    'validity_days' => max(1, (int) ($value['validity_days'] ?? 30)),
                    'expiry_check_time' => $this->normalizeExpiryCheckTime(
                        (string) ($value['expiry_check_time'] ?? self::DEFAULT_EXPIRY_CHECK_TIME),
                    ),
                    'next_display_number' => $nextNumber,
                ]],
            );

            Cache::forget('settings.bonus_program');

            return $number;
        });
    }

    private function normalizeDisplayNumber(int $number): int
    {
        if ($number < 1) {
            return 1;
        }

        if ($number > self::MAX_DISPLAY_NUMBER) {
            return 1;
        }

        return $number;
    }
}
