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
     * Add cook_id foreign key to tenants table.
     *
     * BR-082: Each tenant has exactly one cook at a time.
     * The cook_id column references the users table and is nullable
     * because a tenant may exist before a cook is assigned.
     */
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->foreignId('cook_id')
                ->nullable()
                ->after('is_active')
                ->constrained('users')
                ->nullOnDelete();

            $table->index('cook_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropForeign(['cook_id']);
            $table->dropIndex(['cook_id']);
            $table->dropColumn('cook_id');
        });
    }
};
