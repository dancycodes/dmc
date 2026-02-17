<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * F-061: Admin Complaint Resolution
     * Adds resolution type, refund amount, suspension fields to complaints table.
     */
    public function up(): void
    {
        Schema::table('complaints', function (Blueprint $table) {
            // BR-165: Resolution type (dismiss, partial_refund, full_refund, warning, suspend)
            $table->string('resolution_type', 30)->nullable()->after('resolution_notes');

            // BR-167: Refund amount for partial refunds
            $table->decimal('refund_amount', 12, 2)->nullable()->after('resolution_type');

            // BR-171: Suspension duration in days and end date
            $table->unsignedSmallInteger('suspension_days')->nullable()->after('refund_amount');
            $table->timestamp('suspension_ends_at')->nullable()->after('suspension_days');

            // Index for querying resolved complaints by type
            $table->index('resolution_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('complaints', function (Blueprint $table) {
            $table->dropIndex(['resolution_type']);
            $table->dropColumn([
                'resolution_type',
                'refund_amount',
                'suspension_days',
                'suspension_ends_at',
            ]);
        });
    }
};
