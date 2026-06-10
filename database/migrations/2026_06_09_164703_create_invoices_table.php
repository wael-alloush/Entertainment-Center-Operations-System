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
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number', 32);
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('phone_number', 20);
            $table->unsignedSmallInteger('children_count');
            $table->foreignId('session_duration_id')->constrained();
            $table->string('status', 20)->default('active');
            $table->dateTime('entered_at');
            $table->dateTime('expected_exit_at')->nullable();
            $table->dateTime('exited_at')->nullable();
            $table->decimal('total_amount', 10, 2)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique('invoice_number');
            $table->index('phone_number');
            $table->index('status');
            $table->index('entered_at');
            $table->index(['status', 'entered_at']);
            $table->index(['status', 'expected_exit_at']);
            $table->index(['customer_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
