<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * F-060: Complaint Escalation Queue
     * Stores complaints that can be escalated to admin level.
     *
     * BR-158: Complaints are auto-escalated if cook does not respond within 24h
     * BR-159: Clients and cooks can manually escalate complaints at any time
     * BR-161: Categories: Food Quality, Late Delivery, Missing Items, Wrong Order, Rude Behavior, Other
     * BR-162: Admin statuses: Pending Resolution, Under Review, Resolved, Dismissed
     */
    public function up(): void
    {
        Schema::create('complaints', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id')->nullable(); // FK to orders table added by future migration (F-155+)
            $table->foreignId('client_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('cook_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();

            // Complaint details
            $table->string('category'); // food_quality, late_delivery, missing_items, wrong_order, rude_behavior, other
            $table->text('description');

            // Status tracking
            $table->string('status')->default('open'); // open, responded, escalated, pending_resolution, under_review, resolved, dismissed

            // Escalation fields
            $table->boolean('is_escalated')->default(false);
            $table->string('escalation_reason')->nullable(); // auto_24h, manual_client, manual_cook
            $table->timestamp('escalated_at')->nullable();
            $table->foreignId('escalated_by')->nullable()->constrained('users')->nullOnDelete();

            // Resolution fields (handled by F-061, but schema prepared)
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('resolution_notes')->nullable();
            $table->timestamp('resolved_at')->nullable();

            // Cook response tracking (for BR-158 auto-escalation)
            $table->text('cook_response')->nullable();
            $table->timestamp('cook_responded_at')->nullable();

            $table->timestamp('submitted_at');
            $table->timestamps();

            // Indexes for query performance
            $table->index(['is_escalated', 'status']);
            $table->index('submitted_at');
            $table->index('escalated_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('complaints');
    }
};
