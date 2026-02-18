<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * F-102: Add template_id to cook_schedules for tracking which template
     * was used to create a schedule entry. BR-137: The "applied to" count
     * reflects how many schedule entries were created from a template.
     * F-105 will actually set this value; F-102 just reads it.
     * F-104: On template deletion, template_id is set to null (BR-152).
     */
    public function up(): void
    {
        Schema::table('cook_schedules', function (Blueprint $table) {
            $table->foreignId('template_id')
                ->nullable()
                ->after('pickup_end_time')
                ->constrained('schedule_templates')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cook_schedules', function (Blueprint $table) {
            $table->dropForeign(['template_id']);
            $table->dropColumn('template_id');
        });
    }
};
