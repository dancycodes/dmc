<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * F-115: Cook Tag Management
     * BR-252: Tags are tenant-scoped
     * BR-253: Tag name required in both English and French
     * BR-259: Tag name max length: 50 characters per language
     */
    public function up(): void
    {
        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name_en', 50);
            $table->string('name_fr', 50);
            $table->timestamps();

            // BR-254: Tag name unique within tenant per language
            $table->unique(['tenant_id', 'name_en']);
            $table->unique(['tenant_id', 'name_fr']);
        });

        // Pivot table for many-to-many relationship between meals and tags
        Schema::create('meal_tag', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meal_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['meal_id', 'tag_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('meal_tag');
        Schema::dropIfExists('tags');
    }
};
