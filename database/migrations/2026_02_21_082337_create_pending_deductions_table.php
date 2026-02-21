<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F-174: Cook Auto-Deduction for Refunds
 *
 * Creates the pending_deductions table to track amounts owed by cooks
 * when refunds are issued after the cook has already withdrawn the funds.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('pending_deductions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cook_wallet_id')->constrained('cook_wallets')->cascadeOnDelete();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->decimal('original_amount', 12, 2);
            $table->decimal('remaining_amount', 12, 2);
            $table->string('reason', 255);
            $table->string('source', 50)->default('complaint_refund');
            $table->jsonb('metadata')->nullable();
            $table->timestamp('settled_at')->nullable();
            $table->timestamps();

            $table->index(['cook_wallet_id', 'settled_at']);
            $table->index(['tenant_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pending_deductions');
    }
};
