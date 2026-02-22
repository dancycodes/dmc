<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F-215: Cook Promo Code Creation
 *
 * Creates the promo_codes table for cook-scoped promotional discount codes.
 *
 * BR-533: Alphanumeric code, 3-20 chars, stored uppercase.
 * BR-534: Code unique within tenant.
 * BR-535: Discount types: percentage or fixed.
 * BR-538: Minimum order amount: 0 to 100,000 XAF.
 * BR-539: max_uses 0 = unlimited.
 * BR-540: max_uses_per_client 0 = unlimited.
 * BR-541: starts_at required.
 * BR-542: ends_at optional.
 * BR-543: Promo codes are tenant-scoped.
 * BR-544: Status defaults to active.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promo_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->string('code', 20);
            $table->enum('discount_type', ['percentage', 'fixed']);
            $table->unsignedInteger('discount_value');
            $table->unsignedInteger('minimum_order_amount')->default(0);
            $table->unsignedInteger('max_uses')->default(0)->comment('0 = unlimited');
            $table->unsignedInteger('max_uses_per_client')->default(0)->comment('0 = unlimited');
            $table->unsignedInteger('times_used')->default(0);
            $table->date('starts_at');
            $table->date('ends_at')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();

            // BR-534: Unique per tenant
            $table->unique(['tenant_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promo_codes');
    }
};
