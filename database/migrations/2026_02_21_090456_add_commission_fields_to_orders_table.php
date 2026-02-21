<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * F-175: Commission Deduction on Completion
     * BR-384: Commission transaction references the order and shows the rate used.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('commission_amount', 10, 2)->nullable()->after('wallet_amount');
            $table->decimal('commission_rate', 5, 2)->nullable()->after('commission_amount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['commission_amount', 'commission_rate']);
        });
    }
};
