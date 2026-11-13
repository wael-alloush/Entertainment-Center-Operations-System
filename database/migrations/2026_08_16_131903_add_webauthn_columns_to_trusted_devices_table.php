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
            $table->string('authenticator_type')->nullable()->after('registration_code_id');
            $table->text('credential_id')->nullable()->after('authenticator_type');
            $table->longText('public_key')->nullable()->after('credential_id');
            $table->string('aaguid')->nullable()->after('public_key');
            $table->string('transports')->nullable()->after('aaguid');
            $table->unsignedBigInteger('credential_counter')->default(0)->after('transports');
            $table->string('device_identifier', 64)->nullable()->unique()->after('credential_counter');
            $table->timestamp('last_verified_at')->nullable()->after('device_identifier');

            $table->index('authenticator_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('trusted_devices', function (Blueprint $table) {
            $table->dropIndex(['authenticator_type']);
            $table->dropUnique(['device_identifier']);
            $table->dropColumn([
                'authenticator_type',
                'credential_id',
                'public_key',
                'aaguid',
                'transports',
                'credential_counter',
                'device_identifier',
                'last_verified_at',
            ]);
        });
    }
};
