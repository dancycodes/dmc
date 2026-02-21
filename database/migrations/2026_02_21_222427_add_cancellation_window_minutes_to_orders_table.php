<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F-212: Cancellation Window Configuration
 *
 * BR-500: The cancellation window is snapshotted on the order record at creation time.
 * Stores the cancellation window value (in minutes) that was active when the order was placed,
 * so that subsequent cook changes to the window do not affect existing orders.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedSmallInteger('cancellation_window_minutes')->nullable()->after('commission_rate');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('cancellation_window_minutes');
        });
    }
};
