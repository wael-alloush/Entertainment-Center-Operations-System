<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Models\Customer;
use App\Models\CustomerBonus;
use App\Models\CustomerCredit;
use App\Models\Invoice;
use App\Models\InvoiceExtension;
use App\Models\SessionDuration;
use App\Models\User;
use App\Support\PhoneSearch;
use App\Support\TimeFormatter;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class InvoiceService
{
    public const RECENT_VISIT_DAYS_TO_KEEP = 4;

    public const PREVIOUS_VISITS_PER_PAGE = 15;

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

            $this->refreshStoredVisitCount($data['phone_number']);

            return $invoice->load(['sessionDuration', 'extensions.sessionDuration', 'customer']);
        });
    }

    public function suggestedNextInvoiceNumber(): string
    {
        $recentNumbers = Invoice::query()
            ->where('is_bonus', false)
            ->where('is_credit', false)
            ->orderByDesc('id')
            ->limit(50)
            ->pluck('invoice_number');

        foreach ($recentNumbers as $invoiceNumber) {
            $invoiceNumber = (string) $invoiceNumber;

            if ($invoiceNumber !== '' && ctype_digit($invoiceNumber)) {
                return (string) ((int) $invoiceNumber + 1);
            }
        }

        return '1';
    }

    public function checkout(Invoice $invoice): Invoice
    {
        $invoice->update([
            'status' => InvoiceStatus::Completed,
            'exited_at' => now(),
        ]);

        return $invoice->fresh(['sessionDuration', 'extensions.sessionDuration', 'customer']);
    }

    public function reactivate(Invoice $invoice): Invoice
    {
        $invoice->load(['sessionDuration', 'extensions.sessionDuration']);

        $invoice->update([
            'status' => InvoiceStatus::Active,
            'exited_at' => null,
            'expected_exit_at' => $this->calculateExpectedExitAtFrom($invoice, $invoice->entered_at),
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

    public function calculateExpectedExitAtFrom(Invoice $invoice, Carbon $baseTime): ?Carbon
    {
        $invoice->loadMissing(['sessionDuration', 'extensions.sessionDuration']);

        $sessionDuration = $invoice->sessionDuration;

        if ($sessionDuration->duration_minutes === null) {
            return null;
        }

        $expectedExitAt = $baseTime->copy()->addMinutes($sessionDuration->duration_minutes);

        foreach ($invoice->extensions->sortBy('sort_order') as $extension) {
            $extensionMinutes = $extension->sessionDuration->duration_minutes;

            if ($extensionMinutes === null) {
                return null;
            }

            $expectedExitAt = $expectedExitAt->copy()->addMinutes($extensionMinutes);
        }

        return $expectedExitAt;
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

        $visitCount = $this->storedVisitCountForInvoice($invoice);
        $bonusProgramService = app(BonusProgramService::class);

        return [
            'id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'invoice_display_label' => match (true) {
                $invoice->is_bonus => 'مكافأة '.$invoice->bonus_display_number,
                $invoice->is_credit => 'رصيد '.$invoice->credit_display_number,
                default => $invoice->invoice_number,
            },
            'is_bonus' => $invoice->is_bonus,
            'bonus_display_number' => $invoice->bonus_display_number,
            'is_credit' => $invoice->is_credit,
            'credit_display_number' => $invoice->credit_display_number,
            'counts_as_visit' => $invoice->counts_as_visit,
            'is_bonus_eligible' => $bonusProgramService->isEligible($visitCount),
            'has_pending_bonus' => $invoice->customer_id !== null
                && $bonusProgramService->hasPendingBonusForCustomer($invoice->customer_id),
            'children_count' => $invoice->children_count,
            'phone_number' => $invoice->phone_number,
            'visit_count' => $visitCount,
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
            'expected_exit_at_iso' => $invoice->expected_exit_at?->toIso8601String(),
            'exited_at' => TimeFormatter::formatTime($invoice->exited_at),
            'remaining_minutes' => $remainingMinutes,
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
                    'display_number' => $bonus->display_number,
                    'children_count' => $bonus->children_count,
                    'session_duration' => $bonus->relationLoaded('sessionDuration') && $bonus->sessionDuration
                        ? [
                            'id' => $bonus->sessionDuration->id,
                            'name' => $bonus->sessionDuration->name,
                            'color' => $bonus->sessionDuration->color,
                            'duration_minutes' => $bonus->sessionDuration->duration_minutes,
                        ]
                        : null,
                    'created_at' => TimeFormatter::formatDateTime($bonus->created_at),
                ])
                ->values()
                ->all(),
        ]);
    }

    /**
     * @return array{
     *     phone_number: string,
     *     visit_count: int,
     *     average_children: float,
     *     average_children_visit_threshold: int,
     *     invoices: array{
     *         data: list<array<string, mixed>>,
     *         current_page: int,
     *         last_page: int,
     *         per_page: int,
     *         total: int,
     *         from: int,
     *         to: int
     *     }
     * }
     */
    public function formatPreviousVisits(Invoice $invoice, ?Request $request = null): array
    {
        return $this->formatPreviousVisitsForPhone($invoice->phone_number, $request);
    }

    /**
     * @return array{
     *     phone_number: string,
     *     visit_count: int,
     *     average_children: float,
     *     average_children_visit_threshold: int,
     *     invoices: array{
     *         data: list<array<string, mixed>>,
     *         current_page: int,
     *         last_page: int,
     *         per_page: int,
     *         total: int,
     *         from: int,
     *         to: int
     *     }
     * }
     */
    public function formatPreviousVisitsForPhone(string $phoneNumber, ?Request $request = null): array
    {
        $page = max(1, (int) ($request?->integer('page', 1) ?? 1));
        $perPage = self::PREVIOUS_VISITS_PER_PAGE;
        $summary = $this->previousVisitsSummaryForPhone($phoneNumber);
        $paginator = $this->paginatedPreviousVisitsForPhone($phoneNumber, $page, $perPage, $request);

        return array_merge($summary, [
            'invoices' => [
                'data' => $paginator->items(),
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem() ?? 0,
                'to' => $paginator->lastItem() ?? 0,
            ],
        ]);
    }

    /**
     * @return array{
     *     phone_number: string,
     *     visit_count: int,
     *     average_children: float,
     *     average_children_visit_threshold: int
     * }
     */
    private function previousVisitsSummaryForPhone(string $phoneNumber): array
    {
        $visitThreshold = app(BonusProgramService::class)->visitThreshold();

        $phoneInvoices = Invoice::query()
            ->where('phone_number', $phoneNumber)
            ->whereNot('status', InvoiceStatus::Cancelled)
            ->get(['entered_at', 'children_count', 'is_credit', 'counts_as_visit']);

        $customerId = Customer::query()
            ->where('phone_number', $phoneNumber)
            ->value('id');

        $expiredBonuses = CustomerBonus::query()
            ->whereNotNull('expired_at')
            ->when(
                $customerId !== null,
                fn (Builder $query): Builder => $query->where('customer_id', $customerId),
                fn (Builder $query): Builder => $query->whereRaw('1 = 0'),
            )
            ->get(['expired_at', 'children_count']);

        $countingDayTimestamps = collect();

        foreach ($phoneInvoices as $visit) {
            if ($visit->is_credit && ! $visit->counts_as_visit) {
                continue;
            }

            $day = $visit->entered_at->toDateString();

            if (! $countingDayTimestamps->has($day)) {
                $countingDayTimestamps[$day] = $visit->entered_at->timestamp;
            }
        }

        foreach ($expiredBonuses as $bonus) {
            $day = $bonus->expired_at->toDateString();

            if (! $countingDayTimestamps->has($day)) {
                $countingDayTimestamps[$day] = $bonus->expired_at->timestamp;
            }
        }

        $recentCountingVisitDates = $countingDayTimestamps
            ->sortDesc()
            ->keys()
            ->take($visitThreshold)
            ->values();

        $invoicesByDay = $phoneInvoices
            ->filter(fn (Invoice $visit): bool => ! $visit->is_credit || $visit->counts_as_visit)
            ->groupBy(fn (Invoice $visit): string => $visit->entered_at->toDateString());

        $bonusesByDay = $expiredBonuses->groupBy(
            fn (CustomerBonus $bonus): string => $bonus->expired_at->toDateString(),
        );

        $averageChildren = $recentCountingVisitDates->count() > 0
            ? round(
                $recentCountingVisitDates
                    ->map(function (string $visitDate) use ($invoicesByDay, $bonusesByDay): int {
                        $invoiceMax = (int) ($invoicesByDay->get($visitDate, collect())->max('children_count') ?? 0);
                        $bonusMax = (int) ($bonusesByDay->get($visitDate, collect())->max('children_count') ?? 0);

                        return max($invoiceMax, $bonusMax);
                    })
                    ->avg(),
                2,
            )
            : 0.0;

        return [
            'phone_number' => $phoneNumber,
            'visit_count' => $this->visitDaysCountForPhone($phoneNumber),
            'average_children' => $averageChildren,
            'average_children_visit_threshold' => $visitThreshold,
        ];
    }

    /**
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    private function paginatedPreviousVisitsForPhone(
        string $phoneNumber,
        int $page,
        int $perPage,
        ?Request $request,
    ): LengthAwarePaginator {
        $customerId = Customer::query()
            ->where('phone_number', $phoneNumber)
            ->value('id');

        $invoiceSortQuery = Invoice::query()
            ->where('phone_number', $phoneNumber)
            ->whereNot('status', InvoiceStatus::Cancelled)
            ->selectRaw("'invoice' as row_type, id as row_id, entered_at as sort_at");

        $expiredBonusSortQuery = CustomerBonus::query()
            ->whereNotNull('customer_bonuses.expired_at')
            ->when(
                $customerId !== null,
                fn (Builder $query): Builder => $query->where('customer_bonuses.customer_id', $customerId),
                fn (Builder $query): Builder => $query->whereRaw('1 = 0'),
            )
            ->selectRaw("'expired_bonus' as row_type, customer_bonuses.id as row_id, customer_bonuses.expired_at as sort_at");

        $mergedQuery = DB::query()->fromSub(
            $invoiceSortQuery->unionAll($expiredBonusSortQuery),
            'previous_visits',
        );

        $total = (clone $mergedQuery)->count();

        $pageItems = (clone $mergedQuery)
            ->orderByDesc('sort_at')
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get();

        $invoiceIds = $pageItems
            ->where('row_type', 'invoice')
            ->pluck('row_id')
            ->all();

        $bonusIds = $pageItems
            ->where('row_type', 'expired_bonus')
            ->pluck('row_id')
            ->all();

        $invoices = Invoice::query()
            ->whereIn('id', $invoiceIds)
            ->with(['sessionDuration:id,name,color,duration_minutes', 'extensions.sessionDuration:id,name,color,duration_minutes'])
            ->get([
                'id',
                'invoice_number',
                'children_count',
                'session_duration_id',
                'entered_at',
                'is_bonus',
                'bonus_display_number',
                'is_credit',
                'credit_display_number',
            ])
            ->keyBy('id');

        $expiredBonuses = CustomerBonus::query()
            ->whereIn('id', $bonusIds)
            ->with(['sessionDuration:id,name,color,duration_minutes'])
            ->get(['id', 'children_count', 'session_duration_id', 'expired_at'])
            ->keyBy('id');

        $data = $pageItems
            ->map(function (object $row) use ($invoices, $expiredBonuses): array {
                if ($row->row_type === 'invoice') {
                    return $this->formatPreviousVisitInvoiceRow($invoices[$row->row_id]);
                }

                return $this->formatPreviousVisitExpiredBonusRow($expiredBonuses[$row->row_id]);
            })
            ->all();

        return new LengthAwarePaginator(
            $data,
            $total,
            $perPage,
            $page,
            [
                'path' => $request?->url() ?? '',
                'query' => $request?->query() ?? [],
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function formatPreviousVisitInvoiceRow(Invoice $visit): array
    {
        $originalMinutes = $visit->sessionDuration->duration_minutes ?? 0;
        $extensionsMinutes = (int) $visit->extensions
            ->sum(fn (InvoiceExtension $extension): int => $extension->sessionDuration->duration_minutes ?? 0);

        return [
            'id' => $visit->id,
            'invoice_number' => $visit->invoice_number,
            'invoice_display_label' => $this->previousVisitInvoiceLabel($visit),
            'is_expired_bonus' => false,
            'is_bonus' => $visit->is_bonus,
            'is_credit' => $visit->is_credit,
            'children_count' => $visit->children_count,
            'original_duration_minutes' => $originalMinutes,
            'extensions_duration_minutes' => $extensionsMinutes,
            'total_duration_minutes' => $originalMinutes + $extensionsMinutes,
            'original_session' => [
                'name' => $visit->sessionDuration->name ?? '—',
                'color' => $visit->sessionDuration->color ?? '#6b7280',
            ],
            'extensions' => $visit->extensions
                ->map(fn (InvoiceExtension $extension): array => [
                    'name' => $extension->sessionDuration->name ?? '—',
                    'color' => $extension->sessionDuration->color ?? '#6b7280',
                ])
                ->values()
                ->all(),
            'visit_date' => TimeFormatter::formatDate($visit->entered_at),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formatPreviousVisitExpiredBonusRow(CustomerBonus $bonus): array
    {
        $originalMinutes = $bonus->sessionDuration->duration_minutes ?? 0;

        return [
            'id' => $bonus->id,
            'invoice_number' => 'مكافأة منتهية',
            'invoice_display_label' => 'مكافأة منتهية',
            'is_expired_bonus' => true,
            'is_bonus' => false,
            'is_credit' => false,
            'children_count' => $bonus->children_count,
            'original_duration_minutes' => $originalMinutes,
            'extensions_duration_minutes' => 0,
            'total_duration_minutes' => $originalMinutes,
            'original_session' => [
                'name' => $bonus->sessionDuration->name ?? '—',
                'color' => $bonus->sessionDuration->color ?? '#6b7280',
            ],
            'extensions' => [],
            'visit_date' => TimeFormatter::formatDate($bonus->expired_at),
        ];
    }

    public function update(Invoice $invoice, array $data): Invoice
    {
        return DB::transaction(function () use ($invoice, $data): Invoice {
            $updates = [];
            $previousPhoneNumber = $invoice->phone_number;
            $phoneNumberChanged = false;

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
                $phoneNumberChanged = true;
            }

            if ($updates !== []) {
                $invoice->update($updates);
            }

            if ($phoneNumberChanged) {
                $this->refreshStoredVisitCount($previousPhoneNumber);
                $this->refreshStoredVisitCount($invoice->phone_number);
            }

            return $invoice->fresh(['sessionDuration', 'extensions.sessionDuration', 'customer']);
        });
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

        $storedVisitCountsByPhone = Customer::query()
            ->whereIn('phone_number', $uncachedPhoneNumbers)
            ->pluck('visit_count', 'phone_number');

        foreach ($uncachedPhoneNumbers as $phoneNumber) {
            $this->visitDayCountCache[$phoneNumber] = (int) ($storedVisitCountsByPhone[$phoneNumber] ?? 0);
        }
    }

    public function calculateVisitDaysCountForPhone(string $phoneNumber): int
    {
        $dateExpression = $this->visitDateExpression('entered_at');
        $expiredDateExpression = $this->visitDateExpression('customer_bonuses.expired_at');

        $invoiceDays = Invoice::query()
            ->where('phone_number', $phoneNumber)
            ->where(fn ($query) => $this->applyCountsTowardVisits($query))
            ->selectRaw("{$dateExpression} as visit_day")
            ->groupBy('visit_day')
            ->pluck('visit_day');

        $expiredBonusDays = CustomerBonus::query()
            ->join('customers', 'customers.id', '=', 'customer_bonuses.customer_id')
            ->where('customers.phone_number', $phoneNumber)
            ->whereNotNull('customer_bonuses.expired_at')
            ->selectRaw("{$expiredDateExpression} as visit_day")
            ->groupBy('visit_day')
            ->pluck('visit_day');

        return $invoiceDays
            ->concat($expiredBonusDays)
            ->unique()
            ->filter()
            ->count();
    }

    public function refreshStoredVisitCount(string $phoneNumber): void
    {
        $customer = Customer::query()->where('phone_number', $phoneNumber)->first();

        if ($customer === null) {
            return;
        }

        $newCount = $this->calculateVisitDaysCountForPhone($phoneNumber);

        if ($newCount !== $customer->visit_count) {
            $customer->update(['visit_count' => $newCount]);
        }

        $this->visitDayCountCache[$phoneNumber] = $newCount;
    }

    public function pendingBonusCount(): int
    {
        app(BonusProgramService::class)->expireOverdueBonusesIfNeeded();

        return CustomerBonus::query()->pending()->count();
    }

    public function pendingCreditCount(): int
    {
        return CustomerCredit::query()->pending()->count();
    }

    public function pendingCreditInvoices(Request $request): LengthAwarePaginator
    {
        $search = $request->string('search')->trim()->toString();

        $query = CustomerCredit::query()
            ->pending()
            ->when($search !== '', fn (Builder $creditQuery): Builder => $creditQuery->whereIn(
                'customer_id',
                PhoneSearch::matchingCustomerIdsQuery($search),
            ))
            ->with([
                'sessionDuration:id,name,color,duration_minutes',
                'customer:id,phone_number,visit_count',
            ])
            ->latest();

        $paginator = $query->paginate(25)->withQueryString();

        $this->preloadVisitDaysCountsForPhones(
            $paginator->getCollection()->pluck('customer.phone_number')->filter()->unique()->all(),
        );

        return $paginator->through(fn (CustomerCredit $credit): array => $this->formatPendingCredit($credit));
    }

    /**
     * @return array<string, mixed>
     */
    public function formatPendingCredit(CustomerCredit $credit): array
    {
        $sessionDuration = $credit->sessionDuration;

        return [
            'id' => $credit->id,
            'phone_number' => $credit->customer->phone_number,
            'children_count' => $credit->children_count,
            'visit_count' => (int) $credit->customer->visit_count,
            'counts_as_visit' => $credit->counts_as_visit,
            'session_duration' => [
                'id' => $sessionDuration->id,
                'name' => $sessionDuration->name,
                'color' => $sessionDuration->color,
                'duration_minutes' => $sessionDuration->duration_minutes,
            ],
            'entered_at' => TimeFormatter::formatDateTime($credit->created_at),
        ];
    }

    /**
     * @return array{bonuses: list<array<string, mixed>>, credits: list<array<string, mixed>>}
     */
    public function pendingRecordsForPhone(string $phoneNumber): array
    {
        app(BonusProgramService::class)->expireOverdueBonusesIfNeeded();

        $customer = Customer::query()->where('phone_number', $phoneNumber)->first();

        if ($customer === null) {
            return [
                'bonuses' => [],
                'credits' => [],
            ];
        }

        $bonuses = CustomerBonus::query()
            ->pending()
            ->where('customer_id', $customer->id)
            ->with([
                'sessionDuration:id,name,color,duration_minutes',
                'customer:id,phone_number,visit_count',
            ])
            ->latest()
            ->get();

        $credits = CustomerCredit::query()
            ->pending()
            ->where('customer_id', $customer->id)
            ->with([
                'sessionDuration:id,name,color,duration_minutes',
                'customer:id,phone_number,visit_count',
            ])
            ->latest()
            ->get();

        $this->preloadVisitDaysCountsForPhones([$phoneNumber]);

        return [
            'bonuses' => $bonuses
                ->map(fn (CustomerBonus $bonus): array => $this->formatPendingBonus($bonus))
                ->values()
                ->all(),
            'credits' => $credits
                ->map(fn (CustomerCredit $credit): array => $this->formatPendingCredit($credit))
                ->values()
                ->all(),
        ];
    }

    public function findOrCreateCustomer(string $phoneNumber): Customer
    {
        return $this->findOrLinkCustomer($phoneNumber);
    }

    /**
     * @param  Builder<Invoice>  $query
     * @return Builder<Invoice>
     */
    private function applyCountsTowardVisits(Builder $query): Builder
    {
        return $query
            ->whereNot('status', InvoiceStatus::Cancelled)
            ->where(function (Builder $query): void {
                $query->where('is_credit', false)
                    ->orWhere('counts_as_visit', true);
            });
    }

    /**
     * @return array{from_date: ?string, to_date: ?string}
     */
    public function resolveCompletedDateFilters(Request $request): array
    {
        $today = now()->toDateString();

        if (! ($request->user()?->isAdmin() ?? false)) {
            return [
                'from_date' => $today,
                'to_date' => $today,
            ];
        }

        if (! $request->has('from_date') && ! $request->has('to_date')) {
            return [
                'from_date' => $today,
                'to_date' => $today,
            ];
        }

        $fromDate = $request->string('from_date')->trim()->toString();
        $toDate = $request->string('to_date')->trim()->toString();

        return [
            'from_date' => $fromDate !== '' ? $fromDate : null,
            'to_date' => $toDate !== '' ? $toDate : null,
        ];
    }

    public function completedRowsList(Request $request): LengthAwarePaginator
    {
        app(BonusProgramService::class)->expireOverdueBonusesIfNeeded();

        $perPage = 25;
        $page = max(1, (int) $request->integer('page', 1));
        $search = $request->string('search')->trim()->toString();
        $resolvedDates = $this->resolveCompletedDateFilters($request);
        $fromDate = $resolvedDates['from_date'];
        $toDate = $resolvedDates['to_date'];

        $invoiceSortQuery = Invoice::query()
            ->search($search !== '' ? $search : null)
            ->enteredBetween($fromDate, $toDate)
            ->completed()
            ->selectRaw("'invoice' as row_type, id as row_id, COALESCE(exited_at, entered_at) as sort_at");

        $expiredBonusSortQuery = $this->filteredExpiredBonusQuery($search, $fromDate, $toDate)
            ->selectRaw("'expired_bonus' as row_type, customer_bonuses.id as row_id, customer_bonuses.expired_at as sort_at");

        $unionQuery = $invoiceSortQuery->unionAll($expiredBonusSortQuery);
        $mergedQuery = DB::query()->fromSub($unionQuery, 'completed_rows');

        $total = (clone $mergedQuery)->count();

        $pageItems = (clone $mergedQuery)
            ->orderByDesc('sort_at')
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get();

        $invoiceIds = $pageItems
            ->where('row_type', 'invoice')
            ->pluck('row_id')
            ->all();

        $bonusIds = $pageItems
            ->where('row_type', 'expired_bonus')
            ->pluck('row_id')
            ->all();

        $invoices = Invoice::query()
            ->whereIn('id', $invoiceIds)
            ->with([
                'sessionDuration:id,name,color,duration_minutes',
                'extensions.sessionDuration:id,name,color,duration_minutes',
                'customer:id,visit_count',
            ])
            ->get()
            ->keyBy('id');

        $expiredBonuses = CustomerBonus::query()
            ->whereIn('id', $bonusIds)
            ->with([
                'sessionDuration:id,name,color,duration_minutes',
                'customer:id,phone_number,visit_count',
            ])
            ->get()
            ->keyBy('id');

        $phoneNumbers = $invoices->pluck('phone_number')
            ->merge($expiredBonuses->pluck('customer.phone_number'))
            ->filter()
            ->unique()
            ->all();

        $this->preloadVisitDaysCountsForPhones($phoneNumbers);

        app(BonusProgramService::class)->preloadPendingBonusForCustomers(
            $invoices->pluck('customer_id')->all(),
        );

        $now = now();

        $data = $pageItems
            ->map(function (object $row) use ($invoices, $expiredBonuses, $now): array {
                if ($row->row_type === 'invoice') {
                    return $this->formatInvoice($invoices[$row->row_id], $now);
                }

                return $this->formatExpiredBonus($expiredBonuses[$row->row_id]);
            })
            ->all();

        return new LengthAwarePaginator(
            $data,
            $total,
            $perPage,
            $page,
            [
                'path' => $request->url(),
                'query' => $this->completedPaginationQuery($request, $resolvedDates),
            ],
        );
    }

    /**
     * @param  array{from_date: ?string, to_date: ?string}  $resolvedDates
     * @return array<string, mixed>
     */
    public function completedPaginationQuery(Request $request, array $resolvedDates): array
    {
        $query = $request->query();

        if (! ($request->user()?->isAdmin() ?? false)) {
            return $query;
        }

        if ($resolvedDates['from_date'] === null && $resolvedDates['to_date'] === null) {
            $query['from_date'] = '';
            $query['to_date'] = '';
        } else {
            if ($resolvedDates['from_date'] !== null) {
                $query['from_date'] = $resolvedDates['from_date'];
            }

            if ($resolvedDates['to_date'] !== null) {
                $query['to_date'] = $resolvedDates['to_date'];
            }
        }

        return $query;
    }

    /**
     * @return array{invoice_count: int, children_count: int}
     */
    public function completedSummary(Request $request): array
    {
        $search = $request->string('search')->trim()->toString();
        $resolvedDates = $this->resolveCompletedDateFilters($request);
        $fromDate = $resolvedDates['from_date'];
        $toDate = $resolvedDates['to_date'];

        $invoiceSummary = Invoice::query()
            ->search($search !== '' ? $search : null)
            ->enteredBetween($fromDate, $toDate)
            ->completed()
            ->toBase()
            ->selectRaw('COUNT(*) as invoice_count, COALESCE(SUM(children_count), 0) as children_count')
            ->first();

        $expiredBonusSummary = $this->filteredExpiredBonusQuery($search, $fromDate, $toDate)
            ->toBase()
            ->selectRaw('COUNT(*) as bonus_count, COALESCE(SUM(children_count), 0) as children_count')
            ->first();

        return [
            'invoice_count' => (int) ($invoiceSummary->invoice_count ?? 0) + (int) ($expiredBonusSummary->bonus_count ?? 0),
            'children_count' => (int) ($invoiceSummary->children_count ?? 0) + (int) ($expiredBonusSummary->children_count ?? 0),
        ];
    }

    public function deleteCompletedInvoice(Invoice $invoice): void
    {
        abort_unless($invoice->status === InvoiceStatus::Completed, 422, 'يمكن حذف الفواتير المكتملة فقط.');

        DB::transaction(function () use ($invoice): void {
            $phoneNumber = $invoice->phone_number;

            if ($invoice->is_credit) {
                CustomerCredit::query()->where('invoice_id', $invoice->id)->delete();
            }

            if ($invoice->is_bonus) {
                CustomerBonus::query()->where('invoice_id', $invoice->id)->delete();
            }

            $invoice->update(['status' => InvoiceStatus::Cancelled]);

            $this->refreshStoredVisitCount($phoneNumber);
        });
    }

    public function deleteExpiredBonusRecord(CustomerBonus $bonus): void
    {
        abort_unless($bonus->expired_at !== null, 422, 'يمكن حذف المكافآت المنتهية فقط.');

        DB::transaction(function () use ($bonus): void {
            $phoneNumber = $bonus->customer?->phone_number;

            $bonus->delete();

            if ($phoneNumber !== null) {
                $this->refreshStoredVisitCount($phoneNumber);
            }
        });
    }

    public function pendingBonusInvoices(Request $request): LengthAwarePaginator
    {
        app(BonusProgramService::class)->expireOverdueBonusesIfNeeded();

        $search = $request->string('search')->trim()->toString();

        $query = CustomerBonus::query()
            ->pending()
            ->when($search !== '', fn (Builder $bonusQuery): Builder => $bonusQuery->whereIn(
                'customer_id',
                PhoneSearch::matchingCustomerIdsQuery($search),
            ))
            ->with([
                'sessionDuration:id,name,color,duration_minutes',
                'customer:id,phone_number,visit_count',
            ])
            ->latest();

        $paginator = $query->paginate(25)->withQueryString();

        $this->preloadVisitDaysCountsForPhones(
            $paginator->getCollection()->pluck('customer.phone_number')->filter()->unique()->all(),
        );

        return $paginator->through(fn (CustomerBonus $bonus): array => $this->formatPendingBonus($bonus));
    }

    /**
     * @return array<string, mixed>
     */
    public function formatExpiredBonus(CustomerBonus $bonus): array
    {
        $sessionDuration = $bonus->sessionDuration;

        return [
            'id' => $bonus->id,
            'invoice_number' => '',
            'invoice_display_label' => 'مكافأة منتهية',
            'is_expired_bonus' => true,
            'is_bonus' => false,
            'bonus_display_number' => null,
            'is_bonus_eligible' => app(BonusProgramService::class)->isEligible((int) $bonus->customer->visit_count),
            'has_pending_bonus' => false,
            'children_count' => $bonus->children_count,
            'phone_number' => $bonus->customer->phone_number,
            'visit_count' => (int) $bonus->customer->visit_count,
            'status' => 'expired_bonus',
            'session_duration' => [
                'id' => $sessionDuration->id,
                'name' => $sessionDuration->name,
                'color' => $sessionDuration->color,
                'duration_minutes' => $sessionDuration->duration_minutes,
            ],
            'extensions' => [],
            'entered_at' => TimeFormatter::formatTime($bonus->created_at),
            'expected_exit_at' => TimeFormatter::formatTime($bonus->expires_at),
            'exited_at' => TimeFormatter::formatTime($bonus->expired_at),
            'expired_at' => TimeFormatter::formatTime($bonus->expired_at),
            'remaining_minutes' => null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function formatExpiredBonusDetail(CustomerBonus $bonus): array
    {
        $this->preloadVisitDaysCountsForPhones([$bonus->customer->phone_number]);

        return array_merge($this->formatExpiredBonus($bonus), [
            'customer_id' => $bonus->customer_id,
            'entered_at_full' => TimeFormatter::formatDateTime($bonus->created_at),
            'expected_exit_at_full' => TimeFormatter::formatDateTime($bonus->expires_at),
            'exited_at_full' => TimeFormatter::formatDateTime($bonus->expired_at),
            'status_label' => 'مكافأة منتهية',
            'bonus_count' => 0,
            'bonuses' => [],
        ]);
    }

    /**
     * @return Builder<CustomerBonus>
     */
    private function filteredExpiredBonusQuery(string $search, ?string $fromDate, ?string $toDate): Builder
    {
        return CustomerBonus::query()
            ->whereNotNull('customer_bonuses.expired_at')
            ->when($search !== '', fn (Builder $query): Builder => $query->whereIn(
                'customer_bonuses.customer_id',
                PhoneSearch::matchingCustomerIdsQuery($search),
            ))
            ->when($fromDate !== null, fn (Builder $query): Builder => $query->whereDate('customer_bonuses.expired_at', '>=', $fromDate))
            ->when($toDate !== null, fn (Builder $query): Builder => $query->whereDate('customer_bonuses.expired_at', '<=', $toDate));
    }

    /**
     * @return array<string, mixed>
     */
    public function formatPendingBonus(CustomerBonus $bonus): array
    {
        $sessionDuration = $bonus->sessionDuration;

        return [
            'id' => $bonus->id,
            'phone_number' => $bonus->customer->phone_number,
            'children_count' => $bonus->children_count,
            'visit_count' => (int) $bonus->customer->visit_count,
            'session_duration' => [
                'id' => $sessionDuration->id,
                'name' => $sessionDuration->name,
                'color' => $sessionDuration->color,
                'duration_minutes' => $sessionDuration->duration_minutes,
            ],
            'entered_at' => TimeFormatter::formatDateTime($bonus->created_at),
            'expires_at' => TimeFormatter::formatDateTime($bonus->expires_at),
        ];
    }

    public function storedVisitCountForInvoice(Invoice $invoice): int
    {
        if ($invoice->relationLoaded('customer') && $invoice->customer !== null) {
            return (int) $invoice->customer->visit_count;
        }

        return $this->visitDaysCountForPhone($invoice->phone_number);
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
        return $this->findOrLinkCustomer($phoneNumber);
    }

    private function findOrLinkCustomer(string $phoneNumber): Customer
    {
        return Customer::query()->firstOrCreate(
            ['phone_number' => $phoneNumber],
            ['visit_count' => 0],
        );
    }

    /**
     * @return array{completed_invoices_count: int, cancelled_invoices_count: int}
     */
    public function completedInvoiceCleanupStats(): array
    {
        return [
            'completed_invoices_count' => Invoice::query()->completed()->count(),
            'cancelled_invoices_count' => Invoice::query()->where('status', InvoiceStatus::Cancelled)->count(),
        ];
    }

    /**
     * @return array{deleted: int, kept: int}
     */
    public function pruneOldCompletedInvoices(int $recentVisitDaysToKeep = self::RECENT_VISIT_DAYS_TO_KEEP): array
    {
        $deleted = 0;
        $kept = 0;

        DB::transaction(function () use (&$deleted, &$kept, $recentVisitDaysToKeep): void {
            Invoice::query()
                ->where('status', InvoiceStatus::Cancelled)
                ->orderBy('id')
                ->chunkById(100, function ($invoices) use (&$deleted): void {
                    foreach ($invoices as $invoice) {
                        $this->forceDeleteInvoice($invoice);
                        $deleted++;
                    }
                });

            $phoneNumbers = Invoice::query()
                ->where('status', InvoiceStatus::Completed)
                ->distinct()
                ->pluck('phone_number');

            foreach ($phoneNumbers as $phoneNumber) {
                $protectedDays = $this->protectedVisitDaysForPhone($phoneNumber, $recentVisitDaysToKeep);

                $completedInvoices = Invoice::query()
                    ->where('phone_number', $phoneNumber)
                    ->completed()
                    ->orderBy('id')
                    ->get();

                foreach ($completedInvoices as $invoice) {
                    if ($protectedDays->contains($invoice->entered_at->toDateString())) {
                        $kept++;

                        continue;
                    }

                    $this->forceDeleteInvoice($invoice);
                    $deleted++;
                }
            }
        });

        return [
            'deleted' => $deleted,
            'kept' => $kept,
        ];
    }

    /**
     * @return Collection<int, string>
     */
    private function protectedVisitDaysForPhone(string $phoneNumber, int $limit): Collection
    {
        $days = collect();

        $invoices = Invoice::query()
            ->where('phone_number', $phoneNumber)
            ->where(fn ($query) => $this->applyCountsTowardVisits($query))
            ->orderByDesc('entered_at')
            ->get(['entered_at']);

        foreach ($invoices as $invoice) {
            $day = $invoice->entered_at->toDateString();

            if (! $days->contains($day)) {
                $days->push($day);
            }

            if ($days->count() >= $limit) {
                break;
            }
        }

        return $days;
    }

    private function previousVisitInvoiceLabel(Invoice $visit): string
    {
        return match (true) {
            $visit->is_bonus => 'مكافأة '.($visit->bonus_display_number ?? '—'),
            $visit->is_credit => 'رصيد '.($visit->credit_display_number ?? '—'),
            default => $visit->invoice_number,
        };
    }

    private function forceDeleteInvoice(Invoice $invoice): void
    {
        if ($invoice->is_credit) {
            CustomerCredit::query()->where('invoice_id', $invoice->id)->delete();
        }

        if ($invoice->is_bonus) {
            CustomerBonus::query()->where('invoice_id', $invoice->id)->delete();
        }

        $invoice->delete();
    }
}
