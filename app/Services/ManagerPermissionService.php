<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;

/**
 * F-210: Manager Permission Configuration Service
 *
 * Manages the seven delegatable permissions for tenant managers.
 * Permissions are stored as direct Spatie user permissions (model_has_permissions).
 *
 * BR-473: Configurable permissions: manage-orders, manage-meals, manage-schedule,
 *         manage-locations, view-analytics, manage-complaints, manage-messages.
 * BR-474: Permissions toggled per manager per tenant.
 * BR-475: New managers start with all permissions off.
 * BR-476: Changes take effect immediately on next request.
 * BR-479: Stored as Spatie permissions in model_has_permissions.
 * BR-480: All changes logged via Spatie Activitylog with before/after values.
 */
class ManagerPermissionService
{
    /**
     * The seven delegatable manager permissions (BR-473).
     * Maps display key → Spatie permission name.
     *
     * @var array<string, string>
     */
    public const PERMISSION_MAP = [
        'can-manage-orders' => 'can-manage-orders',
        'can-manage-meals' => 'can-manage-meals',
        'can-manage-schedules' => 'can-manage-schedules',
        'can-manage-locations' => 'can-manage-locations',
        'can-view-cook-analytics' => 'can-view-cook-analytics',
        'can-manage-complaints' => 'can-manage-complaints',
        'can-manage-messages' => 'can-manage-messages',
    ];

    /**
     * List of valid delegatable permission names for validation.
     *
     * @var list<string>
     */
    public const DELEGATABLE_PERMISSIONS = [
        'can-manage-orders',
        'can-manage-meals',
        'can-manage-schedules',
        'can-manage-locations',
        'can-view-cook-analytics',
        'can-manage-complaints',
        'can-manage-messages',
    ];

    /**
     * Permission groups for UI display (BR-473 / UI/UX Notes).
     *
     * @return array<string, array<int, array{key: string, label: string, description: string}>>
     */
    public static function getPermissionGroups(): array
    {
        return [
            __('Business Operations') => [
                [
                    'key' => 'can-manage-orders',
                    'label' => __('Manage Orders'),
                    'description' => __('View and update order statuses, respond to order requests.'),
                ],
                [
                    'key' => 'can-manage-meals',
                    'label' => __('Manage Meals'),
                    'description' => __('Create, edit, and toggle availability of meals and components.'),
                ],
            ],
            __('Coverage') => [
                [
                    'key' => 'can-manage-schedules',
                    'label' => __('Manage Schedule'),
                    'description' => __('Configure operating hours and schedule templates.'),
                ],
                [
                    'key' => 'can-manage-locations',
                    'label' => __('Manage Locations'),
                    'description' => __('Add and manage delivery areas and pickup locations.'),
                ],
            ],
            __('Insights') => [
                [
                    'key' => 'can-view-cook-analytics',
                    'label' => __('View Analytics'),
                    'description' => __('View revenue charts, order analytics, and business performance.'),
                ],
            ],
            __('Engagement') => [
                [
                    'key' => 'can-manage-complaints',
                    'label' => __('Manage Complaints'),
                    'description' => __('View and respond to customer complaints.'),
                ],
                [
                    'key' => 'can-manage-messages',
                    'label' => __('Manage Messages'),
                    'description' => __('Read and reply to order message threads.'),
                ],
            ],
        ];
    }

    /**
     * Get the current permission state for a manager (all 7 permissions).
     *
     * Returns a map of permission name => bool (granted or not).
     * BR-475: New managers start with all permissions off.
     *
     * @return array<string, bool>
     */
    public function getPermissionsState(User $manager): array
    {
        $state = [];

        foreach (self::DELEGATABLE_PERMISSIONS as $permissionName) {
            $state[$permissionName] = $manager->hasDirectPermission($permissionName);
        }

        return $state;
    }

    /**
     * Toggle a single permission for a manager.
     *
     * If the manager has the permission, it is revoked; otherwise it is granted.
     * BR-480: Logs the change with before/after values via Spatie Activitylog.
     *
     * @return array{granted: bool, permission: string}
     */
    public function togglePermission(User $manager, string $permissionName, User $grantedBy): array
    {
        $hadPermission = $manager->hasDirectPermission($permissionName);

        if ($hadPermission) {
            $manager->revokePermissionTo($permissionName);
            $granted = false;
        } else {
            $manager->givePermissionTo($permissionName);
            $granted = true;
        }

        // BR-480: Log the change with before/after values
        activity()
            ->causedBy($grantedBy)
            ->performedOn($manager)
            ->withProperties([
                'permission' => $permissionName,
                'before' => $hadPermission,
                'after' => $granted,
                'manager_id' => $manager->id,
                'manager_email' => $manager->email,
                'manager_name' => $manager->name,
            ])
            ->log($granted ? 'manager_permission_granted' : 'manager_permission_revoked');

        return [
            'granted' => $granted,
            'permission' => $permissionName,
        ];
    }

    /**
     * Check whether a user is a manager for the given tenant.
     * Delegates to the DB pivot table.
     */
    public function isManagerForTenant(User $user, Tenant $tenant): bool
    {
        return DB::table('tenant_managers')
            ->where('tenant_id', $tenant->id)
            ->where('user_id', $user->id)
            ->exists();
    }

    /**
     * Revoke all delegatable permissions for a manager.
     *
     * Called when a manager is removed from a tenant (BR-475 cleanup).
     */
    public function revokeAllPermissions(User $manager, User $revokedBy): void
    {
        foreach (self::DELEGATABLE_PERMISSIONS as $permissionName) {
            if ($manager->hasDirectPermission($permissionName)) {
                $manager->revokePermissionTo($permissionName);
            }
        }

        activity()
            ->causedBy($revokedBy)
            ->performedOn($manager)
            ->withProperties([
                'action' => 'revoke_all_permissions',
                'permissions' => self::DELEGATABLE_PERMISSIONS,
                'manager_id' => $manager->id,
                'manager_email' => $manager->email,
            ])
            ->log('manager_all_permissions_revoked');
    }
}
