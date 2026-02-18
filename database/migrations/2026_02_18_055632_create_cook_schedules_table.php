<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * F-098: Cook Day Schedule Creation
     * Creates the cook_schedules table for managing per-day schedule entries.
     * Each entry represents a service window (e.g., Lunch, Dinner) for a specific
     * day of the week, scoped to a tenant.
     */
    public function up(): void
    {
        Schema::create('cook_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('day_of_week', 10)->comment('monday, tuesday, wednesday, thursday, friday, saturday, sunday');
            $table->boolean('is_available')->default(true);
            $table->string('label')->nullable()->comment('Optional label like Lunch, Dinner');
            $table->unsignedSmallInteger('position')->default(1)->comment('Ordering position within a day');
            $table->timestamps();

            // Index for querying schedules by tenant and day
            $table->index(['tenant_id', 'day_of_week']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cook_schedules');
    }
};
