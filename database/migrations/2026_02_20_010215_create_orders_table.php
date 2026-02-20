<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * F-150: Flutterwave Payment Initiation
     * Creates the orders table needed for payment processing.
     * This table will be extended by future features (F-155 through F-163).
     */
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('cook_id')->nullable()->constrained('users')->nullOnDelete();

            // Order details
            $table->string('order_number', 20)->unique();
            $table->string('status', 30)->default('pending_payment');
            $table->string('delivery_method', 20); // delivery or pickup

            // Location data
            $table->foreignId('town_id')->nullable()->constrained('towns')->nullOnDelete();
            $table->foreignId('quarter_id')->nullable()->constrained('quarters')->nullOnDelete();
            $table->string('neighbourhood', 500)->nullable();
            $table->foreignId('pickup_location_id')->nullable()->constrained('pickup_locations')->nullOnDelete();

            // Amounts
            $table->integer('subtotal')->default(0);
            $table->integer('delivery_fee')->default(0);
            $table->integer('promo_discount')->default(0);
            $table->integer('grand_total')->default(0);

            // Contact
            $table->string('phone', 20);

            // Payment
            $table->string('payment_provider', 30);
            $table->string('payment_phone', 20)->nullable();

            // Items snapshot (JSON array of cart items at time of order)
            $table->jsonb('items_snapshot')->nullable();

            // Timestamps
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();

            $table->timestamps();

            // Indexes
            $table->index(['tenant_id', 'status']);
            $table->index(['client_id', 'created_at']);
            $table->index('status');
        });

        // Nullify existing orphaned order_id values in payment_transactions
        // before adding FK constraint (seeded/test data may reference non-existent orders)
        \DB::table('payment_transactions')->whereNotNull('order_id')->update(['order_id' => null]);

        // Add FK from payment_transactions to orders now that orders table exists
        Schema::table('payment_transactions', function (Blueprint $table) {
            $table->foreign('order_id')->references('id')->on('orders')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payment_transactions', function (Blueprint $table) {
            $table->dropForeign(['order_id']);
        });

        Schema::dropIfExists('orders');
    }
};
