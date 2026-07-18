<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Models\Customer;
use App\Models\CustomerBonus;
use App\Models\CustomerCredit;
use App\Models\Invoice;
use App\Support\PhoneSearch;
use App\Support\TimeFormatter;

class AssistantToolExecutor
{
    public function __construct(
        private InvoiceService $invoiceService,
        private BonusProgramService $bonusProgramService,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function toolDefinitions(): array
    {
        return [
            [
                'type' => 'function',
                'function' => [
                    'name' => 'lookup_customer',
                    'description' => 'البحث عن عميل برقم الجوال وإرجاع عدد الزيارات والبونص والرصيد المعلّق والجلسات النشطة.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'phone_number' => [
                                'type' => 'string',
                                'description' => 'رقم جوال العميل، يفضّل بصيغة 05xxxxxxxx',
                            ],
                        ],
                        'required' => ['phone_number'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'count_active_invoices',
                    'description' => 'إرجاع عدد الفواتير/الجلسات النشطة حالياً.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => (object) [],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'today_summary',
                    'description' => 'ملخص اليوم: الجلسات النشطة، المكتملة اليوم، البونصات والأرصدة المعلّقة.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => (object) [],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'bonus_settings',
                    'description' => 'إعدادات برنامج البونص: عتبة الزيارات ومدة الصلاحية ووقت فحص الانتهاء.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => (object) [],
                    ],
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    public function execute(string $name, array $arguments = []): array
    {
        return match ($name) {
            'lookup_customer' => $this->lookupCustomer((string) ($arguments['phone_number'] ?? '')),
            'count_active_invoices' => $this->countActiveInvoices(),
            'today_summary' => $this->todaySummary(),
            'bonus_settings' => $this->bonusSettings(),
            default => ['error' => 'أداة غير معروفة: '.$name],
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function lookupCustomer(string $phoneNumber): array
    {
        $phoneNumber = trim($phoneNumber);

        if ($phoneNumber === '') {
            return [
                'found' => false,
                'error' => 'رقم الجوال مطلوب.',
            ];
        }

        $customer = Customer::query()
            ->where(fn ($query) => PhoneSearch::applyToCustomerQuery($query, $phoneNumber))
            ->first();

        if ($customer === null) {
            return [
                'found' => false,
                'phone_number' => PhoneSearch::normalizedDigits($phoneNumber) ?: $phoneNumber,
                'message' => 'لم يُعثر على عميل بهذا الرقم.',
            ];
        }

        $this->bonusProgramService->expireOverdueBonusesIfNeeded();

        $pendingBonus = CustomerBonus::query()
            ->pending()
            ->where('customer_id', $customer->id)
            ->with('sessionDuration:id,name,duration_minutes')
            ->latest()
            ->first();

        $pendingCreditsCount = CustomerCredit::query()
            ->pending()
            ->where('customer_id', $customer->id)
            ->count();

        $activeInvoicesCount = Invoice::query()
            ->active()
            ->where('customer_id', $customer->id)
            ->count();

        return [
            'found' => true,
            'phone_number' => $customer->phone_number,
            'visit_count' => $this->invoiceService->visitDaysCountForPhone($customer->phone_number),
            'has_pending_bonus' => $pendingBonus !== null,
            'pending_bonus' => $pendingBonus === null ? null : [
                'expires_at' => TimeFormatter::formatDateTime($pendingBonus->expires_at),
                'session_duration' => $pendingBonus->sessionDuration?->name,
                'children_count' => $pendingBonus->children_count,
            ],
            'pending_credits_count' => $pendingCreditsCount,
            'active_invoices_count' => $activeInvoicesCount,
        ];
    }

    /**
     * @return array{active_invoices_count: int}
     */
    public function countActiveInvoices(): array
    {
        return [
            'active_invoices_count' => Invoice::query()->active()->count(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function todaySummary(): array
    {
        $today = now()->toDateString();

        return [
            'date' => $today,
            'active_invoices_count' => Invoice::query()->active()->count(),
            'completed_today_count' => Invoice::query()
                ->where('status', InvoiceStatus::Completed)
                ->whereDate('exited_at', $today)
                ->count(),
            'pending_bonuses_count' => $this->invoiceService->pendingBonusCount(),
            'pending_credits_count' => $this->invoiceService->pendingCreditCount(),
        ];
    }

    /**
     * @return array{visit_threshold: int, validity_days: int, expiry_check_time: string}
     */
    public function bonusSettings(): array
    {
        $settings = $this->bonusProgramService->settings();

        return [
            'visit_threshold' => $settings['visit_threshold'],
            'validity_days' => $settings['validity_days'],
            'expiry_check_time' => $settings['expiry_check_time'],
        ];
    }
}
