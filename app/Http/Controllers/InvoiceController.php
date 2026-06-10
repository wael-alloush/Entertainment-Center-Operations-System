<?php

namespace App\Http\Controllers;

use App\Enums\InvoiceStatus;
use App\Http\Requests\StoreCustomerBonusRequest;
use App\Http\Requests\StoreInvoiceExtensionRequest;
use App\Http\Requests\StoreInvoiceRequest;
use App\Http\Requests\UpdateInvoiceRequest;
use App\Models\Invoice;
use App\Models\SessionDuration;
use App\Services\InvoiceService;
use App\Support\TimeFormatter;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InvoiceController extends Controller
{
    public function __construct(private InvoiceService $invoiceService) {}

    public function active(Request $request): Response
    {
        return Inertia::render('invoices/active', $this->invoicePageShell($request, InvoiceStatus::Active));
    }

    public function completed(Request $request): Response
    {
        return Inertia::render('invoices/completed', $this->invoicePageShell($request, InvoiceStatus::Completed));
    }

    public function activeChanges(Request $request): JsonResponse
    {
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
                'parentCallMarker:id,name',
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

    public function checkout(Invoice $invoice): RedirectResponse
    {
        abort_unless($invoice->status === InvoiceStatus::Active, 403);

        $this->invoiceService->checkout($invoice);

        return back()->with('success', 'تم إغلاق الفاتورة بنجاح.');
    }

    public function toggleParentCall(Request $request, Invoice $invoice): RedirectResponse
    {
        abort_unless($invoice->status === InvoiceStatus::Active, 403);

        $invoice = $this->invoiceService->toggleParentCall($invoice, $request->user());

        $message = $invoice->needs_parent_call
            ? 'تم وضع علامة اتصال بولي الأمر.'
            : 'تم إزالة علامة الاتصال بولي الأمر.';

        return back()->with('success', $message);
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

        return response()->json($this->invoiceService->formatPreviousVisits($invoice));
    }

    public function storeBonus(StoreCustomerBonusRequest $request, Invoice $invoice): JsonResponse
    {
        $bonus = $this->invoiceService->addBonus($invoice, $request->validated(), $request->user());

        return response()->json([
            'message' => 'تمت إضافة المكافأة بنجاح.',
            'bonus' => [
                'id' => $bonus->id,
                'title' => $bonus->title,
                'notes' => $bonus->notes,
                'created_at' => TimeFormatter::formatDateTime($bonus->created_at),
            ],
            'invoice' => $this->invoiceService->formatInvoiceDetail($this->loadInvoiceDetail($invoice)),
        ]);
    }

    private function loadInvoiceDetail(Invoice $invoice): Invoice
    {
        $invoice->load([
            'sessionDuration:id,name,color,duration_minutes',
            'extensions.sessionDuration:id,name,color,duration_minutes',
            'customer' => fn ($query) => $query
                ->select('id', 'visit_count')
                ->withCount('bonuses')
                ->with(['bonuses' => fn ($bonusQuery) => $bonusQuery->latest()->limit(5)]),
        ]);

        return $invoice;
    }

    /**
     * @return array<string, mixed>
     */
    private function invoicePageShell(Request $request, InvoiceStatus $status): array
    {
        return [
            'sessionDurations' => SessionDuration::activeList(),
            'filters' => $this->invoiceFilters($request),
            'summary' => $this->invoiceSummary($request, $status),
            'invoices' => Inertia::scroll(fn (): \Illuminate\Contracts\Pagination\LengthAwarePaginator => $this->invoiceList($request, $status)),
        ];
    }

    /**
     * @return array{search: string, from_date: string, to_date: string}
     */
    private function invoiceFilters(Request $request): array
    {
        return [
            'search' => $request->string('search')->toString(),
            'from_date' => $request->string('from_date')->toString(),
            'to_date' => $request->string('to_date')->toString(),
        ];
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
        $summaryRow = (clone $this->filteredInvoiceQuery($request, $status))
            ->toBase()
            ->selectRaw('COUNT(*) as invoice_count, COALESCE(SUM(children_count), 0) as children_count')
            ->first();

        return [
            'invoice_count' => (int) ($summaryRow->invoice_count ?? 0),
            'children_count' => (int) ($summaryRow->children_count ?? 0),
        ];
    }

    private function invoiceList(Request $request, InvoiceStatus $status): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $query = (clone $this->filteredInvoiceQuery($request, $status))
            ->with([
                'sessionDuration:id,name,color,duration_minutes',
                'extensions.sessionDuration:id,name,color,duration_minutes',
                'customer:id,visit_count',
                'parentCallMarker:id,name',
            ]);

        $query->latest('entered_at');

        $now = now();
        $paginator = $query->paginate(25)->withQueryString();

        $this->invoiceService->preloadVisitDaysCountsForPhones(
            $paginator->getCollection()->pluck('phone_number')->unique()->all(),
        );

        return $paginator->through(fn (Invoice $invoice): array => $this->invoiceService->formatInvoice($invoice, $now));
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<Invoice>
     */
    private function filteredInvoiceQuery(Request $request, InvoiceStatus $status): \Illuminate\Database\Eloquent\Builder
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
