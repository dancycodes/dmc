<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * F-159: Add override_reason column for admin backward transition overrides.
     * BR-203: Admin override requires a reason and is logged with elevated audit trail.
     */
    public function up(): void
    {
        Schema::table('order_status_transitions', function (Blueprint $table) {
            $table->boolean('is_admin_override')->default(false)->after('new_status');
            $table->text('override_reason')->nullable()->after('is_admin_override');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_status_transitions', function (Blueprint $table) {
            $table->dropColumn(['is_admin_override', 'override_reason']);
        });
    }
};
