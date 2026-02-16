<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * F-052: Add translatable name/description fields and system flag to roles table.
     * The Spatie `name` column stores the machine-friendly role identifier.
     * name_en/name_fr store human-readable display names.
     */
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->string('name_en')->nullable()->after('name');
            $table->string('name_fr')->nullable()->after('name_en');
            $table->text('description')->nullable()->after('name_fr');
            $table->boolean('is_system')->default(false)->after('description');
        });

        // Mark existing system roles
        DB::table('roles')
            ->whereIn('name', ['super-admin', 'admin', 'cook', 'manager', 'client'])
            ->update(['is_system' => true]);

        // Set translatable names for existing system roles
        $systemRoles = [
            'super-admin' => ['name_en' => 'Super Admin', 'name_fr' => 'Super Administrateur'],
            'admin' => ['name_en' => 'Admin', 'name_fr' => 'Administrateur'],
            'cook' => ['name_en' => 'Cook', 'name_fr' => 'Cuisinier'],
            'manager' => ['name_en' => 'Manager', 'name_fr' => 'Gestionnaire'],
            'client' => ['name_en' => 'Client', 'name_fr' => 'Client'],
        ];

        foreach ($systemRoles as $name => $translations) {
            DB::table('roles')
                ->where('name', $name)
                ->update($translations);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->dropColumn(['name_en', 'name_fr', 'description', 'is_system']);
        });
    }
};
