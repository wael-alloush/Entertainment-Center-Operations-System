<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->boolean('is_credit')->default(false)->after('bonus_display_number');
            $table->unsignedTinyInteger('credit_display_number')->nullable()->after('is_credit');
            $table->boolean('counts_as_visit')->default(true)->after('credit_display_number');

            $table->index(['status', 'is_credit']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndex(['status', 'is_credit']);
            $table->dropColumn(['is_credit', 'credit_display_number', 'counts_as_visit']);
        });
    }
};
