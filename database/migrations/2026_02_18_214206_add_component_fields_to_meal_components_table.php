<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * F-118: Meal Component Creation
     * Adds pricing, selling unit, quantity, availability, and position fields
     * to the existing meal_components table (created in F-075).
     */
    public function up(): void
    {
        Schema::table('meal_components', function (Blueprint $table) {
            // BR-280: Price is required, stored as integer (XAF), minimum 1 XAF
            $table->integer('price')->default(0)->after('name_fr');

            // BR-281/BR-282: Selling unit — stored as string for now.
            // F-121 will create selling_units table and migrate to FK.
            $table->string('selling_unit', 50)->default('plate')->after('price');

            // BR-283: Minimum quantity defaults to 0 (no minimum enforced)
            $table->integer('min_quantity')->default(0)->after('selling_unit');

            // BR-284: Maximum quantity defaults to unlimited (null)
            $table->integer('max_quantity')->nullable()->after('min_quantity');

            // BR-285: Available quantity defaults to unlimited (null)
            $table->integer('available_quantity')->nullable()->after('max_quantity');

            // BR-291: Default availability is true
            $table->boolean('is_available')->default(true)->after('available_quantity');

            // BR-290: Position field for display ordering
            $table->integer('position')->default(0)->after('is_available');

            // Index for efficient meal-scoped queries
            $table->index(['meal_id', 'position']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('meal_components', function (Blueprint $table) {
            $table->dropIndex(['meal_id', 'position']);
            $table->dropColumn([
                'price',
                'selling_unit',
                'min_quantity',
                'max_quantity',
                'available_quantity',
                'is_available',
                'position',
            ]);
        });
    }
};
