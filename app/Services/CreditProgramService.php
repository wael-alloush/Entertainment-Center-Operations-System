<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Models\CustomerCredit;
use App\Models\Invoice;
use App\Models\SessionDuration;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class CreditProgramService
{
    public function __construct(private InvoiceService $invoiceService) {}

    public const SETTING_KEY = 'credit_program';

    public const MAX_DISPLAY_NUMBER = 100;

    /**
     * @return array{next_display_number: int}
     */
    public function settings(): array
    {
        $settings = Cache::remember(
            'settings.credit_program',
            now()->addHour(),
            fn (): array => Setting::getValue(self::SETTING_KEY, [
                'next_display_number' => 1,
            ]) ?? [
                'next_display_number' => 1,
            ],
        );

        return [
            'next_display_number' => $this->normalizeDisplayNumber((int) ($settings['next_display_number'] ?? 1)),
        ];
    }

    /**
     * @param  array{phone_number: string, children_count: int, session_duration_id: int, counts_as_visit: bool}  $data
     */
    public function addCredit(array $data, ?User $user = null): CustomerCredit
    {
        $customer = $this->invoiceService->findOrCreateCustomer($data['phone_number']);
        $sessionDuration = SessionDuration::query()->findOrFail($data['session_duration_id']);

        return CustomerCredit::query()->create([
            'customer_id' => $customer->id,
            'invoice_id' => null,
            'children_count' => $data['children_count'],
            'session_duration_id' => $sessionDuration->id,
            'display_number' => null,
            'counts_as_visit' => (bool) $data['counts_as_visit'],
            'created_by' => $user?->id,
        ]);
    }

    /**
     * @param  array{phone_number: string, children_count: int, session_duration_id: int, counts_as_visit: bool}  $data
     */
    public function updatePendingCredit(CustomerCredit $pendingCredit, array $data): CustomerCredit
    {
        abort_if($pendingCredit->display_number !== null || $pendingCredit->invoice_id !== null, 422, 'لا يمكن تعديل رصيد مُفعَّل.');

        $customer = $this->invoiceService->findOrCreateCustomer($data['phone_number']);
        SessionDuration::query()->findOrFail($data['session_duration_id']);

        $pendingCredit->update([
            'customer_id' => $customer->id,
            'children_count' => $data['children_count'],
            'session_duration_id' => $data['session_duration_id'],
            'counts_as_visit' => (bool) $data['counts_as_visit'],
        ]);

        return $pendingCredit->fresh(['sessionDuration', 'customer']);
    }

    public function deletePendingCredit(CustomerCredit $pendingCredit): void
    {
        abort_if($pendingCredit->display_number !== null || $pendingCredit->invoice_id !== null, 422, 'لا يمكن حذف رصيد مُفعَّل.');

        $pendingCredit->delete();
    }

    public function activateCredit(CustomerCredit $pendingCredit, ?User $user = null): Invoice
    {
        abort_if($pendingCredit->display_number !== null || $pendingCredit->invoice_id !== null, 422, 'تم تفعيل هذا الرصيد مسبقاً.');

        return DB::transaction(function () use ($pendingCredit, $user): Invoice {
            $pendingCredit = CustomerCredit::query()->whereKey($pendingCredit->id)->lockForUpdate()->firstOrFail();
            $customer = $pendingCredit->customer()->lockForUpdate()->firstOrFail();
            $sessionDuration = SessionDuration::query()->findOrFail($pendingCredit->session_duration_id);
            $displayNumber = $this->reserveNextDisplayNumber();
            $enteredAt = now();

            $creditInvoice = Invoice::query()->create([
                'invoice_number' => 'رصيد-'.$displayNumber,
                'customer_id' => $customer->id,
                'phone_number' => $customer->phone_number,
                'children_count' => $pendingCredit->children_count,
                'session_duration_id' => $sessionDuration->id,
                'status' => InvoiceStatus::Active,
                'is_credit' => true,
                'credit_display_number' => $displayNumber,
                'counts_as_visit' => $pendingCredit->counts_as_visit,
                'entered_at' => $enteredAt,
                'expected_exit_at' => $this->invoiceService->calculateExpectedExitAt($enteredAt, $sessionDuration),
                'created_by' => $user?->id,
            ]);

            $pendingCredit->update([
                'invoice_id' => $creditInvoice->id,
                'display_number' => $displayNumber,
            ]);

            $this->invoiceService->refreshStoredVisitCount($customer->phone_number);

            return $creditInvoice->fresh(['sessionDuration', 'extensions.sessionDuration', 'customer']);
        });
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
                    'next_display_number' => $nextNumber,
                ]],
            );

            Cache::forget('settings.credit_program');

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
