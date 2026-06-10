<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Support\TimeFormatter;
use App\Models\Customer;
use App\Models\CustomerBonus;
use App\Models\Invoice;
use App\Models\InvoiceExtension;
use App\Models\SessionDuration;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class InvoiceService
{
    /** @var array<string, int> */
    private array $visitDayCountCache = [];

    public function create(array $data, ?User $user = null): Invoice
    {
        return DB::transaction(function () use ($data, $user): Invoice {
            $customer = $this->resolveCustomer($data['phone_number']);
            $sessionDuration = SessionDuration::query()->findOrFail($data['session_duration_id']);
            $enteredAt = now();

            $invoice = Invoice::query()->create([
                'invoice_number' => $data['invoice_number'],
                'customer_id' => $customer->id,
                'phone_number' => $data['phone_number'],
                'children_count' => $data['children_count'],
                'session_duration_id' => $sessionDuration->id,
                'status' => InvoiceStatus::Active,
                'entered_at' => $enteredAt,
                'expected_exit_at' => $this->calculateExpectedExitAt($enteredAt, $sessionDuration),
                'created_by' => $user?->id,
            ]);

            $customer->update([
                'visit_count' => $this->visitDaysCountForPhone($data['phone_number']),
            ]);

            return $invoice->load(['sessionDuration', 'extensions.sessionDuration', 'customer']);
        });
    }

    public function checkout(Invoice $invoice): Invoice
    {
        $invoice->update([
            'status' => InvoiceStatus::Completed,
            'exited_at' => now(),
            'needs_parent_call' => false,
            'parent_call_marked_at' => null,
            'parent_call_marked_by' => null,
        ]);

        return $invoice->fresh(['sessionDuration', 'extensions.sessionDuration', 'customer']);
    }

    public function toggleParentCall(Invoice $invoice, ?User $user): Invoice
    {
        $needsCall = ! $invoice->needs_parent_call;

        $invoice->update([
            'needs_parent_call' => $needsCall,
            'parent_call_marked_at' => $needsCall ? now() : null,
            'parent_call_marked_by' => $needsCall ? $user?->id : null,
        ]);

        return $invoice->fresh(['sessionDuration', 'extensions.sessionDuration', 'customer', 'parentCallMarker']);
    }

    public function reactivate(Invoice $invoice): Invoice
    {
        $invoice->update([
            'status' => InvoiceStatus::Active,
        ]);

        return $invoice->fresh(['sessionDuration', 'extensions.sessionDuration', 'customer']);
    }

    public function extend(Invoice $invoice, int $sessionDurationId): Invoice
    {
        return DB::transaction(function () use ($invoice, $sessionDurationId): Invoice {
            $sessionDuration = SessionDuration::query()->findOrFail($sessionDurationId);
            $sortOrder = ($invoice->extensions()->max('sort_order') ?? 0) + 1;

            InvoiceExtension::query()->create([
                'invoice_id' => $invoice->id,
                'session_duration_id' => $sessionDuration->id,
                'applied_at' => now(),
                'sort_order' => $sortOrder,
            ]);

            if ($sessionDuration->duration_minutes === null) {
                $invoice->update(['expected_exit_at' => null]);
            } else {
                $baseExitTime = $invoice->expected_exit_at ?? now();
                $invoice->update([
                    'expected_exit_at' => $baseExitTime->copy()->addMinutes($sessionDuration->duration_minutes),
                ]);
            }

            return $invoice->fresh(['sessionDuration', 'extensions.sessionDuration', 'customer']);
        });
    }

    public function calculateExpectedExitAt(Carbon $enteredAt, SessionDuration $sessionDuration): ?Carbon
    {
        if ($sessionDuration->duration_minutes === null) {
            return null;
        }

        return $enteredAt->copy()->addMinutes($sessionDuration->duration_minutes);
    }

    /**
     * @return array<string, mixed>
     */
    public function formatInvoice(Invoice $invoice, ?Carbon $now = null): array
    {
        $now ??= now();
        $remainingMinutes = null;

        if ($invoice->status === InvoiceStatus::Active && $invoice->expected_exit_at !== null) {
            $remainingMinutes = (int) $now->diffInMinutes($invoice->expected_exit_at, false);
        }

        return [
            'id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'children_count' => $invoice->children_count,
            'phone_number' => $invoice->phone_number,
            'visit_count' => $this->visitDaysCountForPhone($invoice->phone_number),
            'status' => $invoice->status->value,
            'session_duration' => [
                'id' => $invoice->sessionDuration->id,
                'name' => $invoice->sessionDuration->name,
                'color' => $invoice->sessionDuration->color,
                'duration_minutes' => $invoice->sessionDuration->duration_minutes,
            ],
            'extensions' => $invoice->extensions->map(fn (InvoiceExtension $extension): array => [
                'id' => $extension->id,
                'session_duration' => [
                    'id' => $extension->sessionDuration->id,
                    'name' => $extension->sessionDuration->name,
                    'color' => $extension->sessionDuration->color,
                    'duration_minutes' => $extension->sessionDuration->duration_minutes,
                ],
            ])->values()->all(),
            'entered_at' => TimeFormatter::formatTime($invoice->entered_at),
            'expected_exit_at' => TimeFormatter::formatTime($invoice->expected_exit_at),
            'exited_at' => TimeFormatter::formatTime($invoice->exited_at),
            'remaining_minutes' => $remainingMinutes,
            'needs_parent_call' => $invoice->needs_parent_call,
            'parent_call_marked_by' => $invoice->parentCallMarker?->name,
            'parent_call_marked_at' => TimeFormatter::formatTime($invoice->parent_call_marked_at),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function formatInvoiceDetail(Invoice $invoice): array
    {
        $detail = $this->formatInvoice($invoice);

        $bonuses = $invoice->relationLoaded('customer') && $invoice->customer?->relationLoaded('bonuses')
            ? $invoice->customer->bonuses
            : collect();

        return array_merge($detail, [
            'customer_id' => $invoice->customer_id,
            'entered_at_full' => TimeFormatter::formatDateTime($invoice->entered_at),
            'expected_exit_at_full' => TimeFormatter::formatDateTime($invoice->expected_exit_at),
            'exited_at_full' => TimeFormatter::formatDateTime($invoice->exited_at),
            'status_label' => $invoice->status->label(),
            'bonus_count' => $invoice->customer?->bonuses_count ?? $bonuses->count(),
            'bonuses' => $bonuses
                ->map(fn (CustomerBonus $bonus): array => [
                    'id' => $bonus->id,
                    'title' => $bonus->title,
                    'notes' => $bonus->notes,
                    'created_at' => TimeFormatter::formatDateTime($bonus->created_at),
                ])
                ->values()
                ->all(),
        ]);
    }

    /**
     * @return array{phone_number: string, visit_count: int, average_children: float, invoices: list<array<string, mixed>>}
     */
    public function formatPreviousVisits(Invoice $invoice): array
    {
        $emptyPayload = [
            'phone_number' => $invoice->phone_number,
            'visit_count' => 0,
            'average_children' => 0.0,
            'invoices' => [],
        ];

        $phoneInvoices = Invoice::query()
            ->with(['sessionDuration:id,duration_minutes', 'extensions.sessionDuration:id,duration_minutes'])
            ->where('phone_number', $invoice->phone_number)
            ->where(function ($query) use ($invoice): void {
                $query->where('status', InvoiceStatus::Completed)
                    ->orWhere('id', $invoice->id);
            })
            ->orderByDesc('entered_at')
            ->get(['id', 'invoice_number', 'children_count', 'session_duration_id', 'entered_at', 'status']);

        $recentVisitDates = $phoneInvoices
            ->map(fn (Invoice $visit): string => $visit->entered_at->toDateString())
            ->unique()
            ->values()
            ->take(4);

        $invoicesByDay = $phoneInvoices->groupBy(
            fn (Invoice $visit): string => $visit->entered_at->toDateString(),
        );

        $visitCount = $recentVisitDates->count();

        $averageChildren = $visitCount > 0
            ? round(
                $recentVisitDates
                    ->map(fn (string $visitDate): int => (int) $invoicesByDay
                        ->get($visitDate, collect())
                        ->max('children_count'))
                    ->avg(),
                2,
            )
            : 0.0;

        $invoices = $phoneInvoices
            ->filter(fn (Invoice $visit): bool => $recentVisitDates->contains($visit->entered_at->toDateString()))
            ->map(function (Invoice $visit): array {
                $originalMinutes = $visit->sessionDuration->duration_minutes ?? 0;
                $extensionsMinutes = (int) $visit->extensions
                    ->sum(fn (InvoiceExtension $extension): int => $extension->sessionDuration->duration_minutes ?? 0);

                return [
                    'id' => $visit->id,
                    'invoice_number' => $visit->invoice_number,
                    'children_count' => $visit->children_count,
                    'original_duration_minutes' => $originalMinutes,
                    'extensions_duration_minutes' => $extensionsMinutes,
                    'total_duration_minutes' => $originalMinutes + $extensionsMinutes,
                    'started_at' => TimeFormatter::formatTime($visit->entered_at),
                    'visit_date' => TimeFormatter::formatDate($visit->entered_at),
                ];
            })
            ->values();

        return [
            'phone_number' => $invoice->phone_number,
            'visit_count' => $visitCount,
            'average_children' => $averageChildren,
            'invoices' => $invoices->all(),
        ];
    }

    public function update(Invoice $invoice, array $data): Invoice
    {
        return DB::transaction(function () use ($invoice, $data): Invoice {
            $updates = [];

            if (array_key_exists('invoice_number', $data)) {
                $updates['invoice_number'] = $data['invoice_number'];
            }

            if (array_key_exists('children_count', $data)) {
                $updates['children_count'] = $data['children_count'];
            }

            if (array_key_exists('phone_number', $data) && $data['phone_number'] !== $invoice->phone_number) {
                $customer = $this->findOrLinkCustomer($data['phone_number']);
                $updates['phone_number'] = $data['phone_number'];
                $updates['customer_id'] = $customer->id;
            }

            if ($updates !== []) {
                $invoice->update($updates);
            }

            return $invoice->fresh(['sessionDuration', 'extensions.sessionDuration', 'customer']);
        });
    }

    public function addBonus(Invoice $invoice, array $data, ?User $user = null): CustomerBonus
    {
        abort_if($invoice->customer_id === null, 422, 'لا يوجد عميل مرتبط بهذه الفاتورة.');

        return CustomerBonus::query()->create([
            'customer_id' => $invoice->customer_id,
            'invoice_id' => $invoice->id,
            'title' => $data['title'],
            'notes' => $data['notes'] ?? null,
            'created_by' => $user?->id,
        ]);
    }

    /**
     * @param  array<int, string>  $phoneNumbers
     */
    public function preloadVisitDaysCountsForPhones(array $phoneNumbers): void
    {
        $uncachedPhoneNumbers = array_values(array_filter(
            array_unique($phoneNumbers),
            fn (string $phoneNumber): bool => ! array_key_exists($phoneNumber, $this->visitDayCountCache),
        ));

        if ($uncachedPhoneNumbers === []) {
            return;
        }

        $dateExpression = $this->visitDateExpression('entered_at');

        $countsByPhone = Invoice::query()
            ->whereIn('phone_number', $uncachedPhoneNumbers)
            ->selectRaw("phone_number, {$dateExpression} as visit_day")
            ->groupBy('phone_number', 'visit_day')
            ->get()
            ->groupBy('phone_number')
            ->map(fn ($rows): int => $rows->count());

        foreach ($uncachedPhoneNumbers as $phoneNumber) {
            $this->visitDayCountCache[$phoneNumber] = (int) ($countsByPhone->get($phoneNumber, 0));
        }
    }

    public function visitDaysCountForPhone(string $phoneNumber): int
    {
        if (array_key_exists($phoneNumber, $this->visitDayCountCache)) {
            return $this->visitDayCountCache[$phoneNumber];
        }

        $this->preloadVisitDaysCountsForPhones([$phoneNumber]);

        return $this->visitDayCountCache[$phoneNumber];
    }

    private function visitDateExpression(string $column): string
    {
        return match (DB::connection()->getDriverName()) {
            'sqlite' => "date({$column})",
            'pgsql' => "({$column})::date",
            default => "DATE({$column})",
        };
    }

    private function resolveCustomer(string $phoneNumber): Customer
    {
        $customer = $this->findOrLinkCustomer($phoneNumber);

        $customer->update(['last_visit_at' => now()]);

        return $customer->fresh();
    }

    private function findOrLinkCustomer(string $phoneNumber): Customer
    {
        return Customer::query()->firstOrCreate(
            ['phone_number' => $phoneNumber],
            [
                'visit_count' => 0,
                'first_visit_at' => now(),
            ],
        );
    }
}
