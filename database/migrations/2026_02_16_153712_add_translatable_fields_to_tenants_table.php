<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds translatable columns (name_en, name_fr, description_en, description_fr)
     * to the tenants table for F-045 Tenant Creation Form.
     *
     * Renames existing 'name' column to 'name_en' and adds the new columns.
     */
    public function up(): void
    {
        // Step 1: Rename name to name_en
        Schema::table('tenants', function (Blueprint $table) {
            $table->renameColumn('name', 'name_en');
        });

        // Step 2: Add new columns (name_fr nullable temporarily)
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('name_fr')->nullable()->after('name_en');
            $table->text('description_en')->nullable()->after('custom_domain');
            $table->text('description_fr')->nullable()->after('description_en');
        });

        // Step 3: Copy name_en to name_fr for existing records
        DB::table('tenants')->update(['name_fr' => DB::raw('"name_en"')]);

        // Step 4: Make name_fr non-nullable
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('name_fr')->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['name_fr', 'description_en', 'description_fr']);
        });

        Schema::table('tenants', function (Blueprint $table) {
            $table->renameColumn('name_en', 'name');
        });
    }
};
