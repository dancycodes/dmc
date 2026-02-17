<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * F-059: Payment Monitoring View — foundation table for payment transactions.
     * This table stores all Flutterwave payment transaction data from webhook responses.
     * Related features F-149 through F-154 will populate this table.
     *
     * BR-151: Payment statuses: successful, failed, pending, refunded
     * BR-152: All payment data originates from Flutterwave webhook responses stored locally
     * BR-153: Payment methods: MTN Mobile Money, Orange Money
     * BR-154: Transaction detail shows raw Flutterwave response data
     */
    public function up(): void
    {
        Schema::create('payment_transactions', function (Blueprint $table) {
            $table->id();

            // Order reference (nullable until orders table exists — FK added by F-150 migration)
            $table->unsignedBigInteger('order_id')->nullable()->index();

            // User references
            $table->foreignId('client_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('cook_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();

            // Payment details
            $table->decimal('amount', 12, 2)->default(0);
            $table->string('currency', 5)->default('XAF');
            $table->string('payment_method', 30); // mtn_mobile_money, orange_money
            $table->string('status', 20)->default('pending'); // pending, successful, failed, refunded

            // Flutterwave references
            $table->string('flutterwave_reference', 100)->nullable()->index();
            $table->string('flutterwave_tx_ref', 100)->nullable()->index();
            $table->decimal('flutterwave_fee', 12, 2)->nullable();
            $table->decimal('settlement_amount', 12, 2)->nullable();
            $table->string('payment_channel', 50)->nullable();

            // Webhook data
            $table->jsonb('webhook_payload')->nullable();
            $table->jsonb('status_history')->nullable();
            $table->string('response_code', 20)->nullable();
            $table->text('response_message')->nullable();

            // Refund info
            $table->text('refund_reason')->nullable();
            $table->decimal('refund_amount', 12, 2)->nullable();

            // Customer details as sent to Flutterwave
            $table->string('customer_name', 255)->nullable();
            $table->string('customer_email', 255)->nullable();
            $table->string('customer_phone', 20)->nullable();

            $table->timestamps();

            // Composite index for admin list page queries
            $table->index(['status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_transactions');
    }
};
