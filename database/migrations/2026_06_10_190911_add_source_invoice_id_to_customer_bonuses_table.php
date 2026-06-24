<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('customer_bonuses', function (Blueprint $table) {
            $table->foreignId('source_invoice_id')
                ->nullable()
                ->after('invoice_id')
                ->constrained('invoices')
                ->nullOnDelete();
        });

        $pendingBonuses = DB::table('customer_bonuses')
            ->whereNull('display_number')
            ->whereNotNull('invoice_id')
            ->get(['id', 'invoice_id']);

        foreach ($pendingBonuses as $bonus) {
            DB::table('customer_bonuses')
                ->where('id', $bonus->id)
                ->update([
                    'source_invoice_id' => $bonus->invoice_id,
                    'invoice_id' => null,
                ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customer_bonuses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('source_invoice_id');
        });
    }
};
