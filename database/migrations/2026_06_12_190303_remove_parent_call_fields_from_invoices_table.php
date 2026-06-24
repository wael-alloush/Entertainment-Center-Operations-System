<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndex(['status', 'needs_parent_call']);
            $table->dropConstrainedForeignId('parent_call_marked_by');
            $table->dropColumn(['needs_parent_call', 'parent_call_marked_at']);
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->boolean('needs_parent_call')->default(false)->after('status');
            $table->timestamp('parent_call_marked_at')->nullable()->after('needs_parent_call');
            $table->foreignId('parent_call_marked_by')->nullable()->after('parent_call_marked_at')->constrained('users')->nullOnDelete();

            $table->index(['status', 'needs_parent_call']);
        });
    }
};
