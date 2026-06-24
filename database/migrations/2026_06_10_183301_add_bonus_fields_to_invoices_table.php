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
            $table->boolean('is_bonus')->default(false)->after('status');
            $table->unsignedTinyInteger('bonus_display_number')->nullable()->after('is_bonus');

            $table->index(['status', 'is_bonus']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndex(['status', 'is_bonus']);
            $table->dropColumn(['is_bonus', 'bonus_display_number']);
        });
    }
};
