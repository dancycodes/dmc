<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F-218: Promo Code Application at Checkout
 *
 * Records each time a client successfully uses a promo code on a completed order.
 * BR-582: Record created only when order is placed (not at application time).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promo_code_usages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('promo_code_id')
                ->constrained('promo_codes')
                ->cascadeOnDelete();
            $table->foreignId('order_id')
                ->constrained('orders')
                ->cascadeOnDelete();
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->integer('discount_amount')->default(0);
            $table->timestamps();

            // One usage per order
            $table->unique('order_id');

            // Index for per-client usage count queries (BR-591)
            $table->index(['promo_code_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promo_code_usages');
    }
};
