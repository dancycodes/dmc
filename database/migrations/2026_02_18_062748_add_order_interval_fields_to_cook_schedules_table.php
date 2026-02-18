<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * F-099: Order Time Interval Configuration
     * Adds order interval fields to cook_schedules table.
     * These define when clients can place orders relative to the schedule's open day.
     *
     * BR-106: Start is time + day offset (0 = same day, 1-7 = days before)
     * BR-107: End is time + day offset (0 = same day, 1 = day before)
     * BR-109: Time format is 24-hour (HH:MM)
     */
    public function up(): void
    {
        Schema::table('cook_schedules', function (Blueprint $table) {
            $table->time('order_start_time')->nullable()->after('position')
                ->comment('Order window start time (HH:MM, 24-hour)');
            $table->unsignedTinyInteger('order_start_day_offset')->default(0)->after('order_start_time')
                ->comment('0 = same day, 1 = day before, ... 7 = 7 days before');
            $table->time('order_end_time')->nullable()->after('order_start_day_offset')
                ->comment('Order window end time (HH:MM, 24-hour)');
            $table->unsignedTinyInteger('order_end_day_offset')->default(0)->after('order_end_time')
                ->comment('0 = same day, 1 = day before');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cook_schedules', function (Blueprint $table) {
            $table->dropColumn([
                'order_start_time',
                'order_start_day_offset',
                'order_end_time',
                'order_end_day_offset',
            ]);
        });
    }
};
