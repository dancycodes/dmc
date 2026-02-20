<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F-151: Payment Webhook Handling
 *
 * Creates the wallet_transactions table for tracking cook wallet credits,
 * commission records, and future wallet operations (F-166+).
 *
 * BR-366: Cook wallet credit on successful payment
 * BR-367: Wallet credit initially "unwithdrawable"
 * BR-368: Platform commission recorded separately
 * BR-369: Commission recorded as a separate transaction record
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->foreignId('payment_transaction_id')->nullable()->constrained('payment_transactions')->nullOnDelete();
            $table->string('type', 30);
            $table->decimal('amount', 12, 2)->default(0);
            $table->string('currency', 5)->default('XAF');
            $table->decimal('balance_before', 12, 2)->default(0);
            $table->decimal('balance_after', 12, 2)->default(0);
            $table->boolean('is_withdrawable')->default(false);
            $table->timestamp('withdrawable_at')->nullable();
            $table->string('status', 20)->default('completed');
            $table->text('description')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'type']);
            $table->index(['order_id']);
            $table->index(['tenant_id', 'created_at']);
            $table->index(['is_withdrawable', 'withdrawable_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wallet_transactions');
    }
};
