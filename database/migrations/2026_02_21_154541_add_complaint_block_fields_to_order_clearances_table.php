<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F-186: Complaint-Triggered Payment Block
 *
 * Adds fields to track complaint-related payment blocks on order clearances.
 * BR-217: System checks cook's payment record when complaint is filed.
 * BR-223: Already-withdrawable payments are flagged for review.
 * BR-225: Blocked/flagged amounts excluded from available withdrawal balance.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('order_clearances', function (Blueprint $table) {
            $table->foreignId('complaint_id')
                ->nullable()
                ->after('cook_id')
                ->constrained('complaints')
                ->nullOnDelete();

            $table->boolean('is_flagged_for_review')
                ->default(false)
                ->after('is_cancelled')
                ->comment('BR-223: True when complaint filed after funds became withdrawable');

            $table->timestamp('blocked_at', 0)
                ->nullable()
                ->after('is_flagged_for_review')
                ->comment('BR-218: When the payment was blocked due to complaint');

            $table->timestamp('unblocked_at', 0)
                ->nullable()
                ->after('blocked_at')
                ->comment('BR-220: When the block was lifted on resolution');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_clearances', function (Blueprint $table) {
            $table->dropForeign(['complaint_id']);
            $table->dropColumn([
                'complaint_id',
                'is_flagged_for_review',
                'blocked_at',
                'unblocked_at',
            ]);
        });
    }
};
