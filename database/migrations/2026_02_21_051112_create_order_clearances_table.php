<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * F-171: Order clearance tracking for withdrawable timer logic.
     * Tracks the hold period per order — when it was completed,
     * how long to hold funds, pause/resume state, and when cleared.
     */
    public function up(): void
    {
        Schema::create('order_clearances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cook_id')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('amount', 12, 2)->comment('Amount to become withdrawable (after commission)');
            $table->integer('hold_hours')->default(3)->comment('BR-341: Snapshot of hold period at completion');
            $table->timestamp('completed_at')->comment('BR-333: When order status changed to completed');
            $table->timestamp('withdrawable_at')->comment('Calculated: completed_at + hold_hours');
            $table->timestamp('paused_at')->nullable()->comment('BR-338: When timer was paused by complaint');
            $table->integer('remaining_seconds_at_pause')->nullable()->comment('Seconds remaining when paused');
            $table->timestamp('cleared_at')->nullable()->comment('When funds actually became withdrawable');
            $table->boolean('is_cleared')->default(false)->comment('Whether funds have been transitioned');
            $table->boolean('is_paused')->default(false)->comment('Whether timer is currently paused');
            $table->boolean('is_cancelled')->default(false)->comment('BR-340: Cancelled due to refund');
            $table->timestamps();

            $table->unique('order_id');
            $table->index(['is_cleared', 'is_paused', 'is_cancelled', 'withdrawable_at'], 'order_clearances_eligible_idx');
            $table->index(['tenant_id', 'cook_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_clearances');
    }
};
