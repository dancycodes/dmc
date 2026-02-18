<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F-100: Delivery/Pickup Time Interval Configuration
 *
 * Adds delivery and pickup time interval fields to cook_schedules table.
 * Both intervals are on the open day (day_offset always 0 per BR-116).
 * Start times must be at or after the order interval end time (BR-117/BR-118).
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('cook_schedules', function (Blueprint $table) {
            $table->boolean('delivery_enabled')->default(false)->after('order_end_day_offset')
                ->comment('Whether delivery is enabled for this schedule entry');
            $table->time('delivery_start_time')->nullable()->after('delivery_enabled')
                ->comment('Delivery window start time (HH:MM, 24-hour, same day)');
            $table->time('delivery_end_time')->nullable()->after('delivery_start_time')
                ->comment('Delivery window end time (HH:MM, 24-hour, same day)');

            $table->boolean('pickup_enabled')->default(false)->after('delivery_end_time')
                ->comment('Whether pickup is enabled for this schedule entry');
            $table->time('pickup_start_time')->nullable()->after('pickup_enabled')
                ->comment('Pickup window start time (HH:MM, 24-hour, same day)');
            $table->time('pickup_end_time')->nullable()->after('pickup_start_time')
                ->comment('Pickup window end time (HH:MM, 24-hour, same day)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cook_schedules', function (Blueprint $table) {
            $table->dropColumn([
                'delivery_enabled',
                'delivery_start_time',
                'delivery_end_time',
                'pickup_enabled',
                'pickup_start_time',
                'pickup_end_time',
            ]);
        });
    }
};
