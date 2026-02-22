<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * F-181: Cook Testimonial Moderation
     *
     * Adds moderation timestamp columns to the testimonials table.
     * BR-437: Testimonials have three statuses: pending, approved, rejected.
     * BR-439: The cook can approve, reject, or un-approve testimonials.
     */
    public function up(): void
    {
        Schema::table('testimonials', function (Blueprint $table) {
            $table->timestamp('approved_at')->nullable()->after('status');
            $table->timestamp('rejected_at')->nullable()->after('approved_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('testimonials', function (Blueprint $table) {
            $table->dropColumn(['approved_at', 'rejected_at']);
        });
    }
};
