<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F-182: Approved Testimonials Display
 *
 * Adds is_featured flag to testimonials so cooks can select up to 10
 * featured testimonials for display when more than 10 are approved.
 *
 * BR-447: Maximum 10 testimonials displayed at a time.
 * BR-448: If more than 10 are approved, cook selects featured ones.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('testimonials', function (Blueprint $table) {
            $table->boolean('is_featured')->default(false)->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('testimonials', function (Blueprint $table) {
            $table->dropColumn('is_featured');
        });
    }
};
