<?php

use App\Models\SellingUnit;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Convert meal_components.selling_unit from string keys to selling_unit IDs.
     *
     * F-121: Custom Selling Unit Definition
     * Existing data stores string keys like "plate", "bowl", etc.
     * After F-121, the column stores the selling_unit.id value (as a string).
     *
     * This migration maps existing string values to their corresponding
     * selling_unit IDs. The column type stays varchar(50) for compatibility.
     */
    public function up(): void
    {
        // Map standard unit English names (lowercase) to their string keys
        $keyToName = [
            'plate' => 'Plate',
            'bowl' => 'Bowl',
            'pot' => 'Pot',
            'cup' => 'Cup',
            'piece' => 'Piece',
            'portion' => 'Portion',
            'serving' => 'Serving',
            'pack' => 'Pack',
        ];

        foreach ($keyToName as $key => $name) {
            $sellingUnit = SellingUnit::where('name_en', $name)
                ->where('is_standard', true)
                ->first();

            if ($sellingUnit) {
                DB::table('meal_components')
                    ->where('selling_unit', $key)
                    ->update(['selling_unit' => (string) $sellingUnit->id]);
            }
        }
    }

    /**
     * Reverse: convert IDs back to string keys.
     */
    public function down(): void
    {
        $nameToKey = [
            'Plate' => 'plate',
            'Bowl' => 'bowl',
            'Pot' => 'pot',
            'Cup' => 'cup',
            'Piece' => 'piece',
            'Portion' => 'portion',
            'Serving' => 'serving',
            'Pack' => 'pack',
        ];

        foreach ($nameToKey as $name => $key) {
            $sellingUnit = SellingUnit::where('name_en', $name)
                ->where('is_standard', true)
                ->first();

            if ($sellingUnit) {
                DB::table('meal_components')
                    ->where('selling_unit', (string) $sellingUnit->id)
                    ->update(['selling_unit' => $key]);
            }
        }
    }
};
