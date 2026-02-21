<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F-169: Cook Wallet Dashboard
 *
 * Creates the cook_wallets table to store cook wallet balances.
 * BR-311: Total balance split into withdrawable and unwithdrawable.
 * BR-321: Wallet data is tenant-scoped.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('cook_wallets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->decimal('total_balance', 12, 2)->default(0);
            $table->decimal('withdrawable_balance', 12, 2)->default(0);
            $table->decimal('unwithdrawable_balance', 12, 2)->default(0);
            $table->string('currency', 5)->default('XAF');
            $table->timestamps();

            $table->unique(['tenant_id', 'user_id']);
            $table->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cook_wallets');
    }
};
