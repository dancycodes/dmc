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
     * F-183: Add photo_path column for complaint evidence uploads.
     *
     * BR-187: Photo upload is optional; maximum one image per complaint.
     * BR-188: Accepted image formats: JPEG, PNG, WebP; maximum file size: 5MB.
     */
    public function up(): void
    {
        Schema::table('complaints', function (Blueprint $table) {
            $table->string('photo_path')->nullable()->after('description');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('complaints', function (Blueprint $table) {
            $table->dropColumn('photo_path');
        });
    }
};
