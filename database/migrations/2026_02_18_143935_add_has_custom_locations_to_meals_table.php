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
     * BR-306: By default, meals inherit the cook's global location settings.
     * BR-307: When "Use custom locations" is enabled, the meal uses only the selected locations.
     */
    public function up(): void
    {
        Schema::table('meals', function (Blueprint $table) {
            $table->boolean('has_custom_locations')->default(false)->after('position');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('meals', function (Blueprint $table) {
            $table->dropColumn('has_custom_locations');
        });
    }
};
