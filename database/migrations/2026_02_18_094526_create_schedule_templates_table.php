<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F-101: Create Schedule Template
 *
 * Creates the schedule_templates table for reusable schedule configurations.
 * Templates contain order, delivery, and pickup interval settings that
 * can be applied to multiple schedule days (via F-105).
 *
 * BR-127: Unique name per tenant
 * BR-128: Name required, max 100 chars
 * BR-129: Order interval required
 * BR-130: At least one of delivery/pickup required
 * BR-132: Tenant-scoped
 * BR-135: Templates are independent (copied, not linked)
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('schedule_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('name', 100);

            // Order interval (required - BR-129)
            $table->string('order_start_time', 5); // HH:MM format
            $table->unsignedTinyInteger('order_start_day_offset')->default(0);
            $table->string('order_end_time', 5); // HH:MM format
            $table->unsignedTinyInteger('order_end_day_offset')->default(0);

            // Delivery interval (optional - BR-130)
            $table->boolean('delivery_enabled')->default(false);
            $table->string('delivery_start_time', 5)->nullable();
            $table->string('delivery_end_time', 5)->nullable();

            // Pickup interval (optional - BR-130)
            $table->boolean('pickup_enabled')->default(false);
            $table->string('pickup_start_time', 5)->nullable();
            $table->string('pickup_end_time', 5)->nullable();

            $table->timestamps();

            // BR-127: Unique name per tenant
            $table->unique(['tenant_id', 'name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('schedule_templates');
    }
};
