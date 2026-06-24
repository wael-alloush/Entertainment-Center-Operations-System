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
            $table->timestamp('expires_at')->nullable()->after('display_number');
            $table->timestamp('expired_at')->nullable()->after('expires_at');

            $table->index(['expired_at', 'expires_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customer_bonuses', function (Blueprint $table) {
            $table->dropIndex(['expired_at', 'expires_at']);
            $table->dropColumn(['expires_at', 'expired_at']);
        });
    }
};
