<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * F-096: Meal-Specific Location Override
     * BR-308: Custom locations can include a subset of the cook's delivery quarters and/or pickup locations.
     * BR-309: Custom delivery fees can be set per-quarter for this meal, overriding the global or group fee.
     */
    public function up(): void
    {
        Schema::create('meal_location_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meal_id')->constrained('meals')->cascadeOnDelete();
            $table->foreignId('quarter_id')->nullable()->constrained('quarters')->cascadeOnDelete();
            $table->foreignId('pickup_location_id')->nullable()->constrained('pickup_locations')->cascadeOnDelete();
            $table->integer('custom_delivery_fee')->nullable()->comment('Custom delivery fee in XAF, overrides global/group fee');
            $table->timestamps();

            // A quarter or pickup location can only appear once per meal
            $table->unique(['meal_id', 'quarter_id'], 'meal_location_overrides_meal_quarter_unique');
            $table->unique(['meal_id', 'pickup_location_id'], 'meal_location_overrides_meal_pickup_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('meal_location_overrides');
    }
};
