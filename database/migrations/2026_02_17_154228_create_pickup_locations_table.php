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
     * BR-143: Pickup locations are optional and have no delivery fee.
     */
    public function up(): void
    {
        Schema::create('pickup_locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('town_id')->constrained('towns')->cascadeOnDelete();
            $table->foreignId('quarter_id')->constrained('quarters')->cascadeOnDelete();
            $table->string('name_en');
            $table->string('name_fr');
            $table->string('address');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pickup_locations');
    }
};
