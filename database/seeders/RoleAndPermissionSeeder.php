<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleAndPermissionSeeder extends Seeder
{
    /**
     * The guard name for all roles and permissions.
     */
    private const GUARD = 'web';

    /**
     * Admin-only permissions (platform management).
     *
     * @var list<string>
     */
    private const ADMIN_PERMISSIONS = [
        'can-access-admin-panel',
        'can-create-tenant',
        'can-edit-tenant',
        'can-delete-tenant',
        'can-view-tenants',
        'can-manage-users',
        'can-toggle-user-status',
        'can-manage-roles',
        'can-manage-platform-settings',
        'can-manage-financials',
        'can-view-platform-analytics',
        'can-manage-complaints-escalated',
        'can-manage-payouts',
        'can-view-activity-log',
        'can-export-data',
        'can-view-payments',
        'can-manage-commission',
        'can-send-announcements',
    ];

    /**
     * Cook/tenant management permissions.
     *
     * @var list<string>
     */
    private const COOK_PERMISSIONS = [
        'can-manage-meals',
        'can-manage-meal-components',
        'can-manage-orders',
        'can-manage-brand',
        'can-manage-locations',
        'can-manage-schedules',
        'can-manage-managers',
        'can-manage-promos',
        'can-manage-tags',
        'can-manage-cook-settings',
        'can-view-cook-analytics',
        'can-manage-cook-wallet',
        'can-request-withdrawal',
        'can-manage-testimonials',
        'can-manage-delivery-areas',
        'can-manage-pickup-locations',
        'can-manage-selling-units',
        // F-210: Delegatable manager permissions (cook-level, configurable per tenant)
        'can-manage-complaints',
        'can-manage-messages',
    ];

    /**
     * Client/consumer permissions.
     *
     * @var list<string>
     */
    private const CLIENT_PERMISSIONS = [
        'can-browse-meals',
        'can-place-orders',
        'can-manage-cart',
        'can-manage-wallet',
        'can-rate-orders',
        'can-submit-testimonials',
        'can-submit-complaints',
        'can-send-messages',
        'can-manage-favorites',
        'can-manage-addresses',
        'can-manage-payment-methods',
        'can-view-order-history',
        'can-manage-profile',
        'can-manage-notification-preferences',
    ];

    /**
     * System role metadata: translatable names for the five built-in roles.
     *
     * F-052: Provides human-readable display names for system roles.
     *
     * @var array<string, array{name_en: string, name_fr: string}>
     */
    private const SYSTEM_ROLE_METADATA = [
        'super-admin' => ['name_en' => 'Super Admin', 'name_fr' => 'Super Administrateur'],
        'admin' => ['name_en' => 'Admin', 'name_fr' => 'Administrateur'],
        'cook' => ['name_en' => 'Cook', 'name_fr' => 'Cuisinier'],
        'manager' => ['name_en' => 'Manager', 'name_fr' => 'Gestionnaire'],
        'client' => ['name_en' => 'Client', 'name_fr' => 'Client'],
    ];

    /**
     * Seed the roles and permissions.
     *
     * Uses firstOrCreate for idempotency — re-running does not create duplicates.
     * Uses syncPermissions to ensure role-permission assignments are always correct.
     * F-052: Also sets translatable names and is_system flag on system roles.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // Create all permissions using firstOrCreate for idempotency
        $allPermissionNames = array_merge(
            self::ADMIN_PERMISSIONS,
            self::COOK_PERMISSIONS,
            self::CLIENT_PERMISSIONS,
        );

        foreach ($allPermissionNames as $permissionName) {
            Permission::firstOrCreate(
                ['name' => $permissionName, 'guard_name' => self::GUARD],
            );
        }

        // Flush cache after creating permissions (required if using WithoutModelEvents)
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // Create roles and assign permissions

        // Super-admin: gets ALL permissions
        $superAdmin = Role::firstOrCreate(
            ['name' => 'super-admin', 'guard_name' => self::GUARD],
        );
        $this->applySystemMetadata($superAdmin, 'super-admin');
        $superAdmin->syncPermissions($allPermissionNames);

        // Admin: platform management + client permissions
        $admin = Role::firstOrCreate(
            ['name' => 'admin', 'guard_name' => self::GUARD],
        );
        $this->applySystemMetadata($admin, 'admin');
        $admin->syncPermissions(array_merge(
            self::ADMIN_PERMISSIONS,
            self::CLIENT_PERMISSIONS,
        ));

        // Cook: tenant management + client permissions
        $cook = Role::firstOrCreate(
            ['name' => 'cook', 'guard_name' => self::GUARD],
        );
        $this->applySystemMetadata($cook, 'cook');
        $cook->syncPermissions(array_merge(
            self::COOK_PERMISSIONS,
            self::CLIENT_PERMISSIONS,
        ));

        // Manager: starts with NO permissions (configured per tenant by cook)
        $manager = Role::firstOrCreate(
            ['name' => 'manager', 'guard_name' => self::GUARD],
        );
        $this->applySystemMetadata($manager, 'manager');

        // Client: consumer permissions only
        $client = Role::firstOrCreate(
            ['name' => 'client', 'guard_name' => self::GUARD],
        );
        $this->applySystemMetadata($client, 'client');
        $client->syncPermissions(self::CLIENT_PERMISSIONS);
    }

    /**
     * Apply system role metadata (translatable names and is_system flag).
     *
     * F-052: Ensures system roles always have correct display names.
     */
    private function applySystemMetadata(Role $role, string $roleName): void
    {
        $metadata = self::SYSTEM_ROLE_METADATA[$roleName] ?? null;

        if ($metadata) {
            $role->forceFill([
                'name_en' => $metadata['name_en'],
                'name_fr' => $metadata['name_fr'],
                'is_system' => true,
            ])->save();
        }
    }

    /**
     * Get admin-only permission names.
     *
     * @return list<string>
     */
    public static function adminPermissions(): array
    {
        return self::ADMIN_PERMISSIONS;
    }

    /**
     * Get cook/tenant permission names.
     *
     * @return list<string>
     */
    public static function cookPermissions(): array
    {
        return self::COOK_PERMISSIONS;
    }

    /**
     * Get client/consumer permission names.
     *
     * @return list<string>
     */
    public static function clientPermissions(): array
    {
        return self::CLIENT_PERMISSIONS;
    }

    /**
     * Get all permission names.
     *
     * @return list<string>
     */
    public static function allPermissions(): array
    {
        return array_merge(
            self::ADMIN_PERMISSIONS,
            self::COOK_PERMISSIONS,
            self::CLIENT_PERMISSIONS,
        );
    }
}
