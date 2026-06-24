<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->index(['phone_number', 'status', 'entered_at'], 'invoices_phone_status_entered_idx');
        });

        Schema::table('customer_bonuses', function (Blueprint $table) {
            $table->index(
                ['customer_id', 'invoice_id', 'display_number', 'expired_at'],
                'customer_bonuses_pending_lookup_idx',
            );
            $table->index(['customer_id', 'expired_at'], 'customer_bonuses_customer_expired_idx');
            $table->index(
                ['expires_at', 'expired_at', 'invoice_id', 'display_number'],
                'customer_bonuses_expiry_scan_idx',
            );
        });

        Schema::table('customer_credits', function (Blueprint $table) {
            $table->index(
                ['customer_id', 'invoice_id', 'display_number'],
                'customer_credits_pending_lookup_idx',
            );
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndex('invoices_phone_status_entered_idx');
        });

        Schema::table('customer_bonuses', function (Blueprint $table) {
            $table->dropIndex('customer_bonuses_pending_lookup_idx');
            $table->dropIndex('customer_bonuses_customer_expired_idx');
            $table->dropIndex('customer_bonuses_expiry_scan_idx');
        });

        Schema::table('customer_credits', function (Blueprint $table) {
            $table->dropIndex('customer_credits_pending_lookup_idx');
        });
    }
};
