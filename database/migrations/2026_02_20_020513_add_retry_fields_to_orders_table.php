<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * F-152: Payment Retry with Timeout
     *
     * Adds retry tracking fields to the orders table.
     * BR-377: 15-minute retry window from initial payment attempt.
     * BR-379: Maximum 3 retry attempts allowed per order.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedSmallInteger('retry_count')->default(0)->after('payment_phone');
            $table->timestamp('payment_retry_expires_at')->nullable()->after('retry_count');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['retry_count', 'payment_retry_expires_at']);
        });
    }
};
