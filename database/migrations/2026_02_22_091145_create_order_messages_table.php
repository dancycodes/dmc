<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * F-188: Order Message Thread View
     * BR-239: Each order has exactly one message thread.
     * BR-240: Messages ordered chronologically.
     * BR-241: Initial load shows most recent 20 messages.
     * BR-243: Each message has sender name, sender role, timestamp, and text.
     * BR-244: Thread accessible only by order's client, tenant's cook, and authorized managers.
     */
    public function up(): void
    {
        Schema::create('order_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sender_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('sender_role', 20); // client, cook, manager
            $table->text('body');
            $table->timestamps();

            // Index for efficient thread loading (order + chronological order)
            $table->index(['order_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_messages');
    }
};
