<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * F-197: Favorite Meal Toggle
     * BR-336: Favorite state stored as a user-meal relationship (pivot table).
     * BR-340: A user can favorite unlimited meals.
     */
    public function up(): void
    {
        Schema::create('favorite_meals', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('meal_id');
            $table->timestamp('created_at')->useCurrent();

            $table->primary(['user_id', 'meal_id']);
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('meal_id')->references('id')->on('meals')->cascadeOnDelete();

            $table->index('meal_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('favorite_meals');
    }
};
