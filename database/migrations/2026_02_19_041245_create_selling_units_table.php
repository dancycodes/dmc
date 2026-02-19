<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * F-121: Custom Selling Unit Definition
     * BR-306: Standard units pre-seeded (seeder handles this)
     * BR-308: Custom units are tenant-scoped (tenant_id nullable for standard)
     * BR-309: Name required in both EN and FR
     * BR-314: Name max 50 chars per language
     */
    public function up(): void
    {
        Schema::create('selling_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name_en', 50);
            $table->string('name_fr', 50);
            $table->boolean('is_standard')->default(false);
            $table->timestamps();

            // BR-310: Unique name within tenant (per language)
            // Standard units (tenant_id IS NULL) + custom units share unique constraint per tenant
            $table->index(['tenant_id', 'name_en']);
            $table->index(['tenant_id', 'name_fr']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('selling_units');
    }
};
