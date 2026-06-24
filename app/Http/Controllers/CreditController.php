<?php

namespace App\Http\Controllers;

use App\Http\Requests\ActivateCreditRequest;
use App\Http\Requests\DeleteCreditRequest;
use App\Http\Requests\StoreCreditRequest;
use App\Http\Requests\UpdateCreditRequest;
use App\Models\CustomerCredit;
use App\Models\SessionDuration;
use App\Services\CreditProgramService;
use App\Services\InvoiceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CreditController extends Controller
{
    public function __construct(
        private InvoiceService $invoiceService,
        private CreditProgramService $creditProgramService,
    ) {}

    public function index(Request $request): Response
    {
        return Inertia::render('invoices/credits', [
            'credits' => $this->invoiceService->pendingCreditInvoices($request),
            'sessionDurations' => SessionDuration::activeList(),
            'filters' => [
                'search' => $request->string('search')->toString(),
            ],
        ]);
    }

    public function store(StoreCreditRequest $request): RedirectResponse
    {
        $this->creditProgramService->addCredit($request->validated(), $request->user());

        return redirect()
            ->route('invoices.credits')
            ->with('success', 'تمت إضافة الرصيد بنجاح.');
    }

    public function activate(ActivateCreditRequest $request, CustomerCredit $customerCredit): RedirectResponse
    {
        $this->creditProgramService->activateCredit($customerCredit, $request->user());

        return redirect()
            ->route('invoices.active')
            ->with('success', 'تم تفعيل الرصيد بنجاح.');
    }

    public function update(UpdateCreditRequest $request, CustomerCredit $customerCredit): RedirectResponse
    {
        $this->creditProgramService->updatePendingCredit($customerCredit, $request->validated());

        return redirect()
            ->route('invoices.credits')
            ->with('success', 'تم تحديث الرصيد بنجاح.');
    }

    public function destroy(DeleteCreditRequest $request, CustomerCredit $customerCredit): RedirectResponse
    {
        $this->creditProgramService->deletePendingCredit($customerCredit);

        return redirect()
            ->route('invoices.credits')
            ->with('success', 'تم حذف الرصيد بنجاح.');
    }
}
