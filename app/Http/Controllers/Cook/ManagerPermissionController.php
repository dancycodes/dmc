<?php

namespace App\Http\Controllers\Cook;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ManagerPermissionService;
use Illuminate\Http\Request;

/**
 * F-210: Manager Permission Configuration
 *
 * Allows the cook to toggle individual permissions for each manager.
 * BR-473: Seven delegatable permissions for managers.
 * BR-474: Permissions toggled on/off per manager per tenant.
 * BR-476: Changes take effect immediately.
 * BR-477: Only the cook (not other managers) can configure permissions.
 * BR-479: Permissions stored as Spatie permissions.
 * BR-480: All changes logged via Spatie Activitylog.
 * BR-482: All interactions via Gale.
 */
class ManagerPermissionController extends Controller
{
    public function __construct(
        private readonly ManagerPermissionService $permissionService
    ) {}

    /**
     * Load the permission configuration panel for a manager.
     *
     * Returns the permission panel fragment for inline display.
     * Called via Gale $action (POST) from the managers list page.
     * BR-477: Only the cook can configure manager permissions.
     */
    public function show(Request $request, User $manager): mixed
    {
        $tenant = tenant();
        $user = $request->user();

        // BR-477: Only the cook can configure manager permissions
        if ($tenant->cook_id !== $user->id && ! $user->hasRole('super-admin')) {
            abort(403);
        }

        // Verify the target user is actually a manager for this tenant
        if (! $this->permissionService->isManagerForTenant($manager, $tenant)) {
            abort(404);
        }

        $permissions = $this->permissionService->getPermissionsState($manager);

        return gale()->fragment(
            'cook.managers.permissions',
            'permissions-panel',
            ['manager' => $manager, 'permissions' => $permissions],
        );
    }

    /**
     * Toggle a single permission for a manager.
     *
     * BR-474: Permissions are toggled on/off per manager per tenant.
     * BR-476: Takes effect immediately.
     * BR-480: All changes logged with before/after values.
     */
    public function toggle(Request $request, User $manager): mixed
    {
        $tenant = tenant();
        $user = $request->user();

        // BR-477: Only the cook can configure manager permissions
        if ($tenant->cook_id !== $user->id && ! $user->hasRole('super-admin')) {
            abort(403);
        }

        // Verify the target user is actually a manager for this tenant
        if (! $this->permissionService->isManagerForTenant($manager, $tenant)) {
            abort(404);
        }

        $validated = $request->validateState([
            'permission' => ['required', 'string', 'in:'.implode(',', ManagerPermissionService::DELEGATABLE_PERMISSIONS)],
        ]);

        $result = $this->permissionService->togglePermission(
            manager: $manager,
            permissionName: $validated['permission'],
            grantedBy: $user,
        );

        // Return the updated permissions panel after toggle
        $permissions = $this->permissionService->getPermissionsState($manager);

        return gale()
            ->fragment('cook.managers.permissions', 'permissions-panel', [
                'manager' => $manager,
                'permissions' => $permissions,
            ])
            ->dispatch('toast', [
                'type' => 'success',
                'message' => $result['granted']
                    ? __('Permission granted.')
                    : __('Permission revoked.'),
            ]);
    }
}
