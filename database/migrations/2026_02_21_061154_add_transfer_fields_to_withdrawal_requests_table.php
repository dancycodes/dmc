<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * F-173: Flutterwave Transfer Execution
     * BR-360: Transfer timeout: marked as pending_verification
     * BR-362: Flutterwave transfer reference stored on withdrawal record
     * BR-361: All transfer attempts logged (request, response, status)
     */
    public function up(): void
    {
        Schema::table('withdrawal_requests', function (Blueprint $table) {
            $table->string('flutterwave_transfer_id')->nullable()->after('flutterwave_reference');
            $table->json('flutterwave_response')->nullable()->after('flutterwave_transfer_id');
            $table->string('idempotency_key')->nullable()->unique()->after('flutterwave_response');
            $table->timestamp('completed_at')->nullable()->after('processed_at');
            $table->timestamp('failed_at')->nullable()->after('completed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('withdrawal_requests', function (Blueprint $table) {
            $table->dropColumn([
                'flutterwave_transfer_id',
                'flutterwave_response',
                'idempotency_key',
                'completed_at',
                'failed_at',
            ]);
        });
    }
};
