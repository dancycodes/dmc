<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * F-092: Add Pickup Location
     * BR-284: Address/description is required (free text, max 500 characters).
     * The original migration (F-074) created address as varchar(255).
     */
    public function up(): void
    {
        Schema::table('pickup_locations', function (Blueprint $table) {
            $table->string('address', 500)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pickup_locations', function (Blueprint $table) {
            $table->string('address', 255)->change();
        });
    }
};
