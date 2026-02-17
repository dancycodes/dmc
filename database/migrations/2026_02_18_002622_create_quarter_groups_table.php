<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * F-090: Quarter Group Creation
     * Creates quarter_groups table and quarter_group_quarter pivot table.
     */
    public function up(): void
    {
        Schema::create('quarter_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->integer('delivery_fee')->default(0);
            $table->timestamps();

            // BR-265: Group name unique within tenant
            $table->unique(['tenant_id', 'name']);
        });

        Schema::create('quarter_group_quarter', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quarter_group_id')->constrained('quarter_groups')->cascadeOnDelete();
            $table->foreignId('quarter_id')->constrained('quarters')->cascadeOnDelete();
            $table->timestamps();

            // BR-268: A quarter can belong to at most one group at a time
            $table->unique('quarter_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quarter_group_quarter');
        Schema::dropIfExists('quarter_groups');
    }
};
