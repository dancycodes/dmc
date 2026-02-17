<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * F-075: Schedule & First Meal Step
     * BR-146: Schedule sets availability per day of the week with start and end times.
     * BR-152: Schedule times use 24-hour format.
     * BR-154: Same tables as the full schedule features (F-098 to F-107).
     */
    public function up(): void
    {
        Schema::create('schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->unsignedTinyInteger('day_of_week')->comment('0=Sun, 1=Mon, ..., 6=Sat');
            $table->time('start_time');
            $table->time('end_time');
            $table->boolean('is_available')->default(true);
            $table->timestamps();

            // One schedule entry per day per tenant
            $table->unique(['tenant_id', 'day_of_week']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('schedules');
    }
};
