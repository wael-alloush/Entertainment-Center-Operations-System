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
        Schema::table('customer_bonuses', function (Blueprint $table) {
            $table->unsignedSmallInteger('children_count')->nullable()->after('invoice_id');
            $table->foreignId('session_duration_id')->nullable()->after('children_count')->constrained()->nullOnDelete();
            $table->unsignedTinyInteger('display_number')->nullable()->after('session_duration_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customer_bonuses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('session_duration_id');
            $table->dropColumn(['children_count', 'display_number']);
        });
    }
};
