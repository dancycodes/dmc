<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * F-109: Meal Image Upload & Carousel
     * BR-198: Maximum 3 images per meal
     * BR-199: Accepted formats: jpg/jpeg, png, webp
     * BR-200: Maximum file size: 2MB per image
     * BR-206: Images are tenant-scoped and meal-scoped
     */
    public function up(): void
    {
        Schema::create('meal_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meal_id')->constrained('meals')->cascadeOnDelete();
            $table->string('path');
            $table->string('thumbnail_path')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->string('original_filename');
            $table->string('mime_type', 50);
            $table->unsignedBigInteger('file_size');
            $table->timestamps();

            $table->index(['meal_id', 'position']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('meal_images');
    }
};
