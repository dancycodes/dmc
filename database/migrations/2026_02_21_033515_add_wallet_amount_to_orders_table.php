<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F-168: Client Wallet Payment for Orders
 *
 * Adds wallet_amount column to track how much of an order was paid from wallet.
 * BR-302: Partial wallet + mobile money payment support.
 * BR-307: Wallet transaction record references the wallet portion.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('wallet_amount', 10, 2)->default(0)->after('grand_total');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('wallet_amount');
        });
    }
};
