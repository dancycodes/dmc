<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * F-108: Meal Creation Form
     * Adds status (draft/live), is_available, estimated_prep_time, position,
     * and soft deletes to the meals table. Updates the existing is_active column
     * to is_available semantics and adds the status enum for draft/live lifecycle.
     *
     * BR-190: New meals default to "draft" status
     * BR-191: New meals default to "available" availability
     * BR-189: Meal name unique within tenant per language
     */
    public function up(): void
    {
        Schema::table('meals', function (Blueprint $table) {
            $table->string('status', 20)->default('draft')->after('is_active');
            $table->boolean('is_available')->default(true)->after('status');
            $table->unsignedInteger('estimated_prep_time')->nullable()->after('is_available')->comment('Estimated preparation time in minutes');
            $table->unsignedInteger('position')->default(0)->after('estimated_prep_time')->comment('Display order position');
            $table->softDeletes();

            // BR-189: Unique meal name per tenant per language
            $table->unique(['tenant_id', 'name_en'], 'meals_tenant_name_en_unique');
            $table->unique(['tenant_id', 'name_fr'], 'meals_tenant_name_fr_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('meals', function (Blueprint $table) {
            $table->dropUnique('meals_tenant_name_en_unique');
            $table->dropUnique('meals_tenant_name_fr_unique');
            $table->dropSoftDeletes();
            $table->dropColumn(['status', 'is_available', 'estimated_prep_time', 'position']);
        });
    }
};
