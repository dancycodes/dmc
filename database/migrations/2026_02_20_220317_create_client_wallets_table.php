<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    /**
     * F-166: Client Wallet Dashboard
     *
     * BR-280: Each client has one wallet with a single balance.
     * BR-282: Wallet balance cannot be negative.
     */
    public function up(): void
    {
        Schema::create('client_wallets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->decimal('balance', 12, 2)->default(0);
            $table->string('currency', 5)->default('XAF');
            $table->timestamps();

            $table->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('client_wallets');
    }
};
