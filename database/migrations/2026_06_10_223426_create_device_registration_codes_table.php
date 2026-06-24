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
        Schema::create('device_registration_codes', function (Blueprint $table) {
            $table->id();
            $table->string('code_hash')->unique();
            $table->string('label')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::table('trusted_devices', function (Blueprint $table) {
            $table->foreignId('registration_code_id')->nullable()->after('created_by')->constrained('device_registration_codes')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('trusted_devices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('registration_code_id');
        });

        Schema::dropIfExists('device_registration_codes');
    }
};
