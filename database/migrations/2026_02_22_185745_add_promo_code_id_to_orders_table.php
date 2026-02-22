<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F-218: Promo Code Application at Checkout
 *
 * Adds promo_code_id to orders to link which promo code was applied.
 * BR-582: Usage recorded only when order is successfully placed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('promo_code_id')
                ->nullable()
                ->after('promo_discount')
                ->constrained('promo_codes')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeignIdFor(\App\Models\PromoCode::class);
        });
    }
};
