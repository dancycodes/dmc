<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone', 20)->after('email');
            $table->boolean('is_active')->default(true)->after('password');
            $table->string('profile_photo_path')->nullable()->after('is_active');
            $table->string('preferred_language', 5)->default('en')->after('profile_photo_path');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['phone', 'is_active', 'profile_photo_path', 'preferred_language']);
        });
    }
};
