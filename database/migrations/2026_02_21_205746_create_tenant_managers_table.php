<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F-209: Cook Creates Manager Role
 *
 * Creates the tenant_managers pivot table to track which users
 * have the manager role scoped to a specific tenant.
 *
 * BR-464: Manager role is scoped to the current tenant.
 * BR-466: Removal revokes the role for this tenant only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_managers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['tenant_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_managers');
    }
};
