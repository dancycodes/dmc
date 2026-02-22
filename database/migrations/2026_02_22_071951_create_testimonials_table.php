<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * F-180: Testimonial Submission Form
     *
     * Creates the testimonials table for client testimonials submitted on tenant landing pages.
     * BR-427: Each client can submit one testimonial per cook (per tenant) — enforced via unique index.
     * BR-429: Submitted testimonials have status 'pending' until cook approves (F-181).
     */
    public function up(): void
    {
        Schema::create('testimonials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->text('text');
            $table->string('status', 20)->default('pending'); // pending, approved, rejected
            $table->timestamps();

            // BR-427: One testimonial per client per tenant
            $table->unique(['tenant_id', 'user_id']);

            // Indexes for common queries
            $table->index(['tenant_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('testimonials');
    }
};
