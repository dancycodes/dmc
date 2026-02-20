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
     * F-156: Add notes column for client order notes.
     *
     * BR-174: Order notes from the client are displayed if present.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->text('notes')->nullable()->after('items_snapshot');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('notes');
        });
    }
};
