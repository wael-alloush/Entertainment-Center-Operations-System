<?php

use App\Models\Customer;
use App\Services\InvoiceService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->unsignedInteger('visit_count')->default(0)->after('phone_number');
            $table->index('visit_count');
        });

        $invoiceService = app(InvoiceService::class);

        Customer::query()
            ->orderBy('id')
            ->chunkById(100, function ($customers) use ($invoiceService): void {
                foreach ($customers as $customer) {
                    $customer->update([
                        'visit_count' => $invoiceService->calculateVisitDaysCountForPhone($customer->phone_number),
                    ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropIndex(['visit_count']);
            $table->dropColumn('visit_count');
        });
    }
};
