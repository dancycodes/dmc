<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('label', 50);
            $table->string('provider', 20); // mtn_momo or orange_money
            $table->string('phone', 20); // stored as +237XXXXXXXXX
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            // BR-148: Label unique per user
            $table->unique(['user_id', 'label']);

            // Index for user-scoped queries
            $table->index(['user_id', 'is_default']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_methods');
    }
};
