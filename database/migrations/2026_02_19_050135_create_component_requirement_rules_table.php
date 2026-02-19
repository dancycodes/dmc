<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * F-122: Meal Component Requirement Rules
     * BR-316: Three rule types: requires_any_of, requires_all_of, incompatible_with
     * BR-317: Rules reference other components within the same meal only
     */
    public function up(): void
    {
        Schema::create('component_requirement_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meal_component_id')
                ->constrained('meal_components')
                ->cascadeOnDelete();
            $table->string('rule_type', 30);
            $table->timestamps();

            $table->index('meal_component_id');
        });

        Schema::create('component_requirement_rule_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rule_id')
                ->constrained('component_requirement_rules')
                ->cascadeOnDelete();
            $table->foreignId('target_component_id')
                ->constrained('meal_components')
                ->cascadeOnDelete();

            $table->unique(['rule_id', 'target_component_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('component_requirement_rule_targets');
        Schema::dropIfExists('component_requirement_rules');
    }
};
