<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * F-074: Delivery Areas Step
     * Links a delivery area to a quarter with a delivery fee.
     * BR-141: Delivery fee >= 0 XAF (0 = free delivery).
     * BR-142: Delivery fee stored as integer in XAF.
     */
    public function up(): void
    {
        Schema::create('delivery_area_quarters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_area_id')->constrained('delivery_areas')->cascadeOnDelete();
            $table->foreignId('quarter_id')->constrained('quarters')->cascadeOnDelete();
            $table->unsignedInteger('delivery_fee')->default(0);
            $table->timestamps();

            $table->unique(['delivery_area_id', 'quarter_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('delivery_area_quarters');
    }
};
