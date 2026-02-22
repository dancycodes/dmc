<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F-148: Order Scheduling for Future Date
 *
 * Adds scheduled_date column to orders table so orders can be
 * placed for a future date rather than the next available slot.
 *
 * BR-343: The order stores the scheduled date for cook reference.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->date('scheduled_date')->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('scheduled_date');
        });
    }
};
