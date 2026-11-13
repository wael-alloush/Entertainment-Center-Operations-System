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
        Schema::table('trusted_devices', function (Blueprint $table) {
            $table->text('webauthn_user_handle')->nullable()->after('public_key');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('trusted_devices', function (Blueprint $table) {
            $table->dropColumn('webauthn_user_handle');
        });
    }
};
