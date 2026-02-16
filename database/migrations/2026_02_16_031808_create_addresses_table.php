<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Delivery addresses belong to users (NOT tenant-scoped, BR-126).
     * Users can have up to 5 saved addresses (BR-119). The first address
     * is automatically set as default (BR-125).
     */
    public function up(): void
    {
        Schema::create('addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('label', 50);
            $table->foreignId('town_id')->constrained('towns')->restrictOnDelete();
            $table->foreignId('quarter_id')->constrained('quarters')->restrictOnDelete();
            $table->string('neighbourhood')->nullable();
            $table->text('additional_directions')->nullable();
            $table->boolean('is_default')->default(false);
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'label']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('addresses');
    }
};
