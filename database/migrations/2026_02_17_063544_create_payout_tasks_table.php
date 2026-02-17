<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * F-065: Manual Payout Task Queue
     * Stores failed Flutterwave transfer records that require manual admin intervention.
     */
    public function up(): void
    {
        Schema::create('payout_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cook_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('currency', 5)->default('XAF');
            $table->string('mobile_money_number', 20);
            $table->string('payment_method', 30); // mtn_mobile_money, orange_money
            $table->text('failure_reason');
            $table->string('flutterwave_reference', 100)->nullable();
            $table->string('flutterwave_transfer_id', 100)->nullable();
            $table->jsonb('flutterwave_response')->nullable();
            $table->string('status', 20)->default('pending'); // pending, completed, manually_completed
            $table->integer('retry_count')->default(0);
            $table->string('reference_number', 255)->nullable(); // For manual completion proof
            $table->text('resolution_notes')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('requested_at'); // Original withdrawal request date
            $table->timestamp('last_retry_at')->nullable();
            $table->timestamps();

            // Indexes for common queries
            $table->index(['status', 'requested_at']);
            $table->index('cook_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payout_tasks');
    }
};
