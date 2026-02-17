<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * F-075: Schedule & First Meal Step
     * BR-148: Meal name required in both English and French.
     * BR-149: Meal price must be > 0 XAF.
     * BR-151: Created meals default to is_active = true.
     * BR-154: Same tables as the full meal features (F-108 to F-125).
     */
    public function up(): void
    {
        Schema::create('meals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('name_en');
            $table->string('name_fr');
            $table->text('description_en')->nullable();
            $table->text('description_fr')->nullable();
            $table->unsignedInteger('price')->comment('Price in XAF');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['tenant_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('meals');
    }
};
