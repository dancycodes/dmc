<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * F-172: Cook Withdrawal Request
     * BR-351: Withdrawal record with status "pending"
     * BR-352: Withdrawable balance decremented immediately
     */
    public function up(): void
    {
        Schema::create('withdrawal_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cook_wallet_id')->constrained('cook_wallets')->cascadeOnDelete();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('currency', 5)->default('XAF');
            $table->string('mobile_money_number', 20);
            $table->string('mobile_money_provider', 30)->nullable();
            $table->string('status', 20)->default('pending');
            $table->string('flutterwave_reference')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamp('requested_at');
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'user_id', 'requested_at']);
            $table->index(['status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('withdrawal_requests');
    }
};
