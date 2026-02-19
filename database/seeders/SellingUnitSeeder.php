<?php

namespace Database\Seeders;

use App\Models\SellingUnit;
use Illuminate\Database\Seeder;

class SellingUnitSeeder extends Seeder
{
    /**
     * Seed the standard selling units.
     *
     * F-121: Custom Selling Unit Definition
     * BR-306: Standard units pre-seeded: plate, bowl, pot, cup, piece, portion, serving, pack
     * BR-315: Standard units have pre-defined translations for English and French
     *
     * Edge Case: Idempotent — does not create duplicates when run multiple times.
     */
    public function run(): void
    {
        foreach (SellingUnit::STANDARD_UNITS as $key => $translations) {
            SellingUnit::firstOrCreate(
                [
                    'name_en' => $translations['en'],
                    'is_standard' => true,
                ],
                [
                    'tenant_id' => null,
                    'name_fr' => $translations['fr'],
                ]
            );
        }
    }
}
