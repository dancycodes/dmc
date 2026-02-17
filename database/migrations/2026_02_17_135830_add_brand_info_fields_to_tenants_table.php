<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * F-072: Brand Info Step — adds contact and social fields to tenants.
     * name_en, name_fr, description_en, description_fr already exist.
     */
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('whatsapp', 20)->nullable()->after('description_fr');
            $table->string('phone', 20)->nullable()->after('whatsapp');
            $table->string('social_facebook', 500)->nullable()->after('phone');
            $table->string('social_instagram', 500)->nullable()->after('social_facebook');
            $table->string('social_tiktok', 500)->nullable()->after('social_instagram');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn([
                'whatsapp',
                'phone',
                'social_facebook',
                'social_instagram',
                'social_tiktok',
            ]);
        });
    }
};
