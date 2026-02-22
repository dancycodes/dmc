<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * F-196: Favorite Cook Toggle
     * BR-326: Favorite state stored as a user-cook relationship (pivot table).
     * BR-330: A user can favorite unlimited cooks.
     */
    public function up(): void
    {
        Schema::create('favorite_cooks', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('cook_user_id');
            $table->timestamp('created_at')->useCurrent();

            $table->primary(['user_id', 'cook_user_id']);
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('cook_user_id')->references('id')->on('users')->cascadeOnDelete();

            $table->index('cook_user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('favorite_cooks');
    }
};
