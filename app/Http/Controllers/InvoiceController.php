<?php

namespace App\Http\Controllers;

use App\Enums\InvoiceStatus;
use App\Http\Requests\ActivateInvoiceBonusRequest;
use App\Http\Requests\AddInvoiceBonusRequest;
use App\Http\Requests\DeleteCompletedInvoiceRequest;
use App\Http\Requests\DeleteExpiredBonusRecordRequest;
use App\Http\Requests\LookupPendingRecordsRequest;
use App\Http\Requests\StoreInvoiceExtensionRequest;
use App\Http\Requests\StoreInvoiceRequest;
use App\Http\Requests\UpdateInvoiceRequest;
use App\Models\CustomerBonus;
use App\Models\Invoice;
use App\Models\SessionDuration;
use App\Services\BonusProgramService;
use App\Services\InvoiceService;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

class InvoiceController extends Controller
{
    public function __construct(
        private InvoiceService $invoiceService,
        private BonusProgramService $bonusProgramService,
    ) {}

    public function active(Request $request): Response
    {
        return Inertia::render('invoices/active', $this->invoicePageShell($request, InvoiceStatus::Active));
    }

    public function completed(Request $request): Response
    {
        return Inertia::render('invoices/completed', $this->invoicePageShell($request, InvoiceStatus::Completed));
    }

    public function pendingBonuses(Request $request): Response
    {
        return Inertia::render('invoices/bonuses', [
            'bonuses' => $this->invoiceService->pendingBonusInvoices($request),
            'bonusProgram' => [
                'validity_days' => $this->bonusProgramService->validityDays(),
            ],
            'filters' => [
                'search' => $request->string('search')->toString(),
            ],
        ]);
    }

    public function eligibleBonusesWithoutPending(Request $request): JsonResponse
    {
        return response()->json([
            'customers' => $this->bonusProgramService->paginateEligibleCustomersWithoutPendingBonus($request),
        ]);
    }

    public function activeChanges(Request $request): JsonResponse
    {
        $this->bonusProgramService->expireOverdueBonusesIfNeeded();

        $since = $request->filled('since')
            ? Carbon::parse($request->string('since')->toString())
            : now()->subSeconds(15);

        $now = now();

        $updated = Invoice::query()
            ->active()
            ->where('updated_at', '>', $since)
            ->with([
                'sessionDuration:id,name,color,duration_minutes',
                'extensions.sessionDuration:id,name,color,duration_minutes',
                'customer:id,visit_count',
            ])
            ->latest('entered_at')
            ->get();

        $removedIds = Invoice::query()
            ->completed()
            ->where('updated_at', '>', $since)
            ->pluck('id')
            ->values()
            ->all();

        $this->invoiceService->preloadVisitDaysCountsForPhones(
            $updated->pluck('phone_number')->unique()->all(),
        );

        $this->bonusProgramService->preloadPendingBonusForCustomers(
            $updated->pluck('customer_id')->all(),
        );

        $hasChanges = $updated->isNotEmpty() || $removedIds !== [];

        return response()->json([
            'synced_at' => $now->toIso8601String(),
            'updated' => $updated
                ->map(fn (Invoice $invoice): array => $this->invoiceService->formatInvoice($invoice, $now))
                ->values()
                ->all(),
            'removed_ids' => $removedIds,
            'summary' => $hasChanges ? $this->activeInvoiceSummary() : null,
        ]);
    }

    public function store(StoreInvoiceRequest $request): RedirectResponse
    {
        $this->invoiceService->create($request->validated(), $request->user());

        return redirect()->route('invoices.active')->with('success', 'تمت إضافة الفاتورة بنجاح.');
    }

    public function pendingRecordsForPhone(LookupPendingRecordsRequest $request): JsonResponse
    {
        return response()->json(
            $this->invoiceService->pendingRecordsForPhone($request->validated('phone_number')),
        );
    }

    public function previousVisitsForPhone(LookupPendingRecordsRequest $request): JsonResponse
    {
        return response()->json(
            $this->invoiceService->formatPreviousVisitsForPhone($request->validated('phone_number'), $request),
        );
    }

    public function checkout(Invoice $invoice): RedirectResponse
    {
        abort_unless($invoice->status === InvoiceStatus::Active, 403);

        $this->invoiceService->checkout($invoice);

        return back()->with('success', 'تم إغلاق الفاتورة بنجاح.');
    }

    public function reactivate(Request $request, Invoice $invoice): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);
        abort_unless($invoice->status === InvoiceStatus::Completed, 403);

        $this->invoiceService->reactivate($invoice);

        return redirect()
            ->route('invoices.active')
            ->with('success', 'تمت إعادة تفعيل الفاتورة بنجاح.');
    }

    public function destroyCompleted(DeleteCompletedInvoiceRequest $request, Invoice $invoice): RedirectResponse
    {
        $label = match (true) {
            $invoice->is_credit => 'الرصيد',
            $invoice->is_bonus => 'المكافأة',
            default => 'الفاتورة',
        };

        $this->invoiceService->deleteCompletedInvoice($invoice);

        return back()->with('success', "تم حذف {$label} من السجل.");
    }

    public function destroyExpiredBonus(DeleteExpiredBonusRecordRequest $request, CustomerBonus $customerBonus): RedirectResponse
    {
        $this->invoiceService->deleteExpiredBonusRecord($customerBonus);

        return back()->with('success', 'تم حذف المكافأة المنتهية من السجل.');
    }

    public function extend(StoreInvoiceExtensionRequest $request, Invoice $invoice): RedirectResponse
    {
        abort_unless($invoice->status === InvoiceStatus::Active, 403);

        if ($invoice->expected_exit_at === null) {
            return back()->with('error', 'لا يمكن تمديد فاتورة بوقت مفتوح.');
        }

        $this->invoiceService->extend($invoice, $request->integer('session_duration_id'));

        return back()->with('success', 'تم التمديد بنجاح.');
    }

    public function show(Invoice $invoice): JsonResponse
    {
        return response()->json([
            'invoice' => $this->invoiceService->formatInvoiceDetail($this->loadInvoiceDetail($invoice)),
        ]);
    }

    public function update(UpdateInvoiceRequest $request, Invoice $invoice): JsonResponse
    {
        $invoice = $this->invoiceService->update($invoice, $request->validated());

        return response()->json([
            'message' => 'تم تحديث البيانات بنجاح.',
            'invoice' => $this->invoiceService->formatInvoiceDetail($this->loadInvoiceDetail($invoice)),
        ]);
    }

    public function visits(Request $request, Invoice $invoice): JsonResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        return response()->json($this->invoiceService->formatPreviousVisits($invoice, $request));
    }

    public function storeBonus(AddInvoiceBonusRequest $request, Invoice $invoice): JsonResponse
    {
        $this->bonusProgramService->addBonus($invoice, $request->validated(), $request->user());

        return response()->json([
            'message' => 'تمت إضافة المكافأة إلى قائمة الانتظار.',
            'invoice' => $this->invoiceService->formatInvoiceDetail($this->loadInvoiceDetail($invoice)),
        ]);
    }

    public function activateCustomerBonus(ActivateInvoiceBonusRequest $request, CustomerBonus $customerBonus): RedirectResponse
    {
        try {
            $this->bonusProgramService->activateBonus($customerBonus, $request->user());
        } catch (HttpException $exception) {
            if ($exception->getStatusCode() === 422) {
                return redirect()
                    ->back()
                    ->with('error', $exception->getMessage());
            }

            throw $exception;
        }

        return redirect()
            ->route('invoices.active')
            ->with('success', 'تم تفعيل المكافأة بنجاح.');
    }

    public function showExpiredBonus(CustomerBonus $customerBonus): JsonResponse
    {
        abort_unless($customerBonus->expired_at !== null, 404);

        $customerBonus->load([
            'sessionDuration:id,name,color,duration_minutes',
            'customer:id,phone_number,visit_count',
        ]);

        return response()->json([
            'invoice' => $this->invoiceService->formatExpiredBonusDetail($customerBonus),
        ]);
    }

    public function expiredBonusVisits(Request $request, CustomerBonus $customerBonus): JsonResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);
        abort_unless($customerBonus->expired_at !== null, 404);

        $customerBonus->load('customer:id,phone_number,visit_count');

        return response()->json(
            $this->invoiceService->formatPreviousVisitsForPhone($customerBonus->customer->phone_number, $request),
        );
    }

    private function loadInvoiceDetail(Invoice $invoice): Invoice
    {
        $invoice->load([
            'sessionDuration:id,name,color,duration_minutes',
            'extensions.sessionDuration:id,name,color,duration_minutes',
            'customer' => fn ($query) => $query
                ->select('id', 'visit_count')
                ->withCount('bonuses')
                ->with(['bonuses' => fn ($bonusQuery) => $bonusQuery->with('sessionDuration:id,name,color,duration_minutes')->latest()->limit(5)]),
        ]);

        return $invoice;
    }

    /**
     * @return array<string, mixed>
     */
    private function invoicePageShell(Request $request, InvoiceStatus $status): array
    {
        $shell = [
            'sessionDurations' => SessionDuration::activeList(),
            'bonusProgram' => [
                'visit_threshold' => $this->bonusProgramService->visitThreshold(),
                'validity_days' => $this->bonusProgramService->validityDays(),
            ],
            'filters' => $this->invoiceFilters($request, $status),
            'canFilterCompletedByDate' => $status === InvoiceStatus::Completed && ($request->user()?->isAdmin() ?? false),
            'summary' => $this->invoiceSummary($request, $status),
            'invoices' => $this->invoiceList($request, $status),
        ];

        if ($status === InvoiceStatus::Active) {
            $shell['nextInvoiceNumber'] = $this->invoiceService->suggestedNextInvoiceNumber();
        }

        return $shell;
    }

    /**
     * @return array{search: string, from_date: string, to_date: string}
     */
    private function invoiceFilters(Request $request, InvoiceStatus $status): array
    {
        $filters = [
            'search' => $request->string('search')->toString(),
            'from_date' => '',
            'to_date' => '',
        ];

        if ($status === InvoiceStatus::Completed) {
            $dates = $this->invoiceService->resolveCompletedDateFilters($request);
            $filters['from_date'] = $dates['from_date'] ?? '';
            $filters['to_date'] = $dates['to_date'] ?? '';
        }

        return $filters;
    }

    /**
     * @return array{invoice_count: int, children_count: int}
     */
    private function activeInvoiceSummary(): array
    {
        $summaryRow = Invoice::query()
            ->active()
            ->toBase()
            ->selectRaw('COUNT(*) as invoice_count, COALESCE(SUM(children_count), 0) as children_count')
            ->first();

        return [
            'invoice_count' => (int) ($summaryRow->invoice_count ?? 0),
            'children_count' => (int) ($summaryRow->children_count ?? 0),
        ];
    }

    /**
     * @return array{invoice_count: int, children_count: int}
     */
    private function invoiceSummary(Request $request, InvoiceStatus $status): array
    {
        if ($status === InvoiceStatus::Completed) {
            return $this->invoiceService->completedSummary($request);
        }

        $summaryRow = (clone $this->filteredInvoiceQuery($request, $status))
            ->toBase()
            ->selectRaw('COUNT(*) as invoice_count, COALESCE(SUM(children_count), 0) as children_count')
            ->first();

        return [
            'invoice_count' => (int) ($summaryRow->invoice_count ?? 0),
            'children_count' => (int) ($summaryRow->children_count ?? 0),
        ];
    }

    private function invoiceList(Request $request, InvoiceStatus $status): LengthAwarePaginator
    {
        if ($status === InvoiceStatus::Completed) {
            return $this->invoiceService->completedRowsList($request);
        }

        $query = (clone $this->filteredInvoiceQuery($request, $status))
            ->with([
                'sessionDuration:id,name,color,duration_minutes',
                'extensions.sessionDuration:id,name,color,duration_minutes',
                'customer:id,visit_count',
            ]);

        $query->latest('entered_at');

        $now = now();
        $paginator = $query->paginate(25)->withQueryString();

        $this->invoiceService->preloadVisitDaysCountsForPhones(
            $paginator->getCollection()->pluck('phone_number')->unique()->all(),
        );

        $this->bonusProgramService->preloadPendingBonusForCustomers(
            $paginator->getCollection()->pluck('customer_id')->all(),
        );

        return $paginator->through(fn (Invoice $invoice): array => $this->invoiceService->formatInvoice($invoice, $now));
    }

    /**
     * @return Builder<Invoice>
     */
    private function filteredInvoiceQuery(Request $request, InvoiceStatus $status): Builder
    {
        $query = Invoice::query()
            ->search($request->string('search')->toString() ?: null)
            ->enteredBetween(
                $request->string('from_date')->toString() ?: null,
                $request->string('to_date')->toString() ?: null,
            );

        if ($status === InvoiceStatus::Active) {
            $query->active();
        } else {
            $query->completed();
        }

        return $query;
    }
}
