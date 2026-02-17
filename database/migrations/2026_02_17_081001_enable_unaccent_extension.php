<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Enable the PostgreSQL unaccent extension for accent-insensitive search.
     *
     * BR-084: Search is case-insensitive and accent-insensitive.
     * F-068: Discovery Search requires accent-insensitive matching.
     */
    public function up(): void
    {
        DB::statement('CREATE EXTENSION IF NOT EXISTS unaccent');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP EXTENSION IF EXISTS unaccent');
    }
};
