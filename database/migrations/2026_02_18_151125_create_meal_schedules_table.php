<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F-106: Meal Schedule Override
 *
 * Creates the meal_schedules table for meal-specific schedule overrides.
 * Same structure as cook_schedules but with a meal_id foreign key.
 * When a meal has entries in this table, it uses its own schedule
 * instead of the cook's default schedule.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('meal_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('meal_id')->constrained('meals')->cascadeOnDelete();
            $table->string('day_of_week', 10)->comment('monday, tuesday, wednesday, thursday, friday, saturday, sunday');
            $table->boolean('is_available')->default(true);
            $table->string('label')->nullable()->comment('Optional label like Lunch, Dinner');
            $table->smallInteger('position')->default(1)->comment('Ordering position within a day');

            // F-099 equivalent: Order time interval
            $table->time('order_start_time')->nullable()->comment('Order window start time (HH:MM, 24-hour)');
            $table->smallInteger('order_start_day_offset')->default(0)->comment('0 = same day, 1 = day before, ... 7 = 7 days before');
            $table->time('order_end_time')->nullable()->comment('Order window end time (HH:MM, 24-hour)');
            $table->smallInteger('order_end_day_offset')->default(0)->comment('0 = same day, 1 = day before');

            // F-100 equivalent: Delivery/Pickup time intervals
            $table->boolean('delivery_enabled')->default(false)->comment('Whether delivery is enabled for this schedule entry');
            $table->time('delivery_start_time')->nullable()->comment('Delivery window start time (HH:MM, 24-hour, same day)');
            $table->time('delivery_end_time')->nullable()->comment('Delivery window end time (HH:MM, 24-hour, same day)');
            $table->boolean('pickup_enabled')->default(false)->comment('Whether pickup is enabled for this schedule entry');
            $table->time('pickup_start_time')->nullable()->comment('Pickup window start time (HH:MM, 24-hour, same day)');
            $table->time('pickup_end_time')->nullable()->comment('Pickup window end time (HH:MM, 24-hour, same day)');

            $table->timestamps();

            // Indexes
            $table->index(['tenant_id', 'meal_id', 'day_of_week']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('meal_schedules');
    }
};
