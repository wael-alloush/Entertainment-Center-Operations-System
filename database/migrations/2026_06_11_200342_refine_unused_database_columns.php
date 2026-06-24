<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropIndex(['visit_count']);
            $table->dropColumn(['visit_count', 'first_visit_at', 'last_visit_at']);
        });

        Schema::table('customer_bonuses', function (Blueprint $table) {
            $table->dropColumn('notes');
        });

        Schema::table('invoice_extensions', function (Blueprint $table) {
            $table->dropColumn('applied_at');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->unsignedInteger('visit_count')->default(0)->after('phone_number');
            $table->timestamp('first_visit_at')->nullable()->after('visit_count');
            $table->timestamp('last_visit_at')->nullable()->after('first_visit_at');
            $table->index('visit_count');
        });

        Schema::table('customer_bonuses', function (Blueprint $table) {
            $table->text('notes')->nullable()->after('title');
        });

        Schema::table('invoice_extensions', function (Blueprint $table) {
            $table->dateTime('applied_at')->after('session_duration_id');
        });
    }
};
