<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * F-211: Manager Dashboard Access
 *
 * Provides data and logic for manager access to the cook dashboard.
 * Handles permission checks, tenant switching, and activity logging.
 *
 * BR-483: Managers see only sections corresponding to their granted permissions.
 * BR-484: Navigation items for unpermitted sections are not rendered.
 * BR-485: Direct URL access to unpermitted sections returns 403.
 * BR-486: Manager with no permissions sees a "no permissions" message.
 * BR-487: Managers with multiple tenants see a tenant switcher.
 * BR-488: Switcher lists all tenants where user has manager role.
 * BR-489: Switching tenants navigates to the other tenant's domain.
 * BR-490: Manager dashboard access is logged via Spatie Activitylog.
 * BR-491: Cook-reserved sections are never accessible to managers.
 */
class ManagerDashboardService
{
    /**
     * Sections permanently reserved for cooks only (BR-491).
     * Managers can NEVER access these regardless of permissions.
     *
     * @var list<string>
     */
    public const COOK_RESERVED_PATHS = [
        'dashboard/wallet',
        'dashboard/settings',
        'dashboard/managers',
        'dashboard/profile',
        'dashboard/promo-codes',
        'dashboard/setup',
    ];

    /**
     * Permission required per dashboard path segment.
     * null = accessible to all dashboard users.
     *
     * @var array<string, string|null>
     */
    public const PATH_PERMISSIONS = [
        'dashboard' => null,               // Home — always visible
        'dashboard/stats/refresh' => null, // Stats polling endpoint — all dashboard users
        'dashboard/orders' => 'can-manage-orders',
        'dashboard/meals' => 'can-manage-meals',
        'dashboard/tags' => 'can-manage-meals',
        'dashboard/selling-units' => 'can-manage-meals',
        'dashboard/schedule' => 'can-manage-schedules',
        'dashboard/locations' => 'can-manage-locations',
        'dashboard/analytics' => 'can-view-cook-analytics',
        'dashboard/analytics/orders' => 'can-view-cook-analytics',
        'dashboard/complaints' => 'can-manage-complaints',
        'dashboard/messages' => 'can-manage-messages',
        'dashboard/testimonials' => 'can-manage-testimonials',
    ];

    /**
     * Determine whether the authenticated user is a manager for the current tenant
     * (and NOT the cook).
     */
    public function isManager(User $user, Tenant $tenant): bool
    {
        // The cook is never "a manager" for their own tenant
        if ($tenant->cook_id === $user->id) {
            return false;
        }

        return DB::table('tenant_managers')
            ->where('tenant_id', $tenant->id)
            ->where('user_id', $user->id)
            ->exists();
    }

    /**
     * Check whether a manager has the required permission for a given request path.
     *
     * BR-483: Only sections matching granted permissions are accessible.
     * BR-491: Cook-reserved sections always return false for managers.
     *
     * @param  string  $requestPath  e.g. 'dashboard/orders/123'
     */
    public function managerCanAccessPath(User $manager, string $requestPath): bool
    {
        // BR-491: Immediately block cook-reserved paths
        foreach (self::COOK_RESERVED_PATHS as $reservedPath) {
            if ($requestPath === $reservedPath || str_starts_with($requestPath, $reservedPath.'/')) {
                return false;
            }
        }

        // Find the most-specific matching permission entry.
        // 'dashboard' matches ONLY the exact path; sub-paths need their own entry.
        $requiredPermission = null;
        $longestMatch = -1;

        foreach (self::PATH_PERMISSIONS as $path => $permission) {
            $matches = $path === 'dashboard'
                ? $requestPath === 'dashboard'
                : ($requestPath === $path || str_starts_with($requestPath, $path.'/'));

            if ($matches && strlen($path) > $longestMatch) {
                $longestMatch = strlen($path);
                $requiredPermission = $permission;
            }
        }

        // No matching path rule — deny by default for managers
        if ($longestMatch === -1) {
            return false;
        }

        // null permission = always accessible (e.g. dashboard home)
        if ($requiredPermission === null) {
            return true;
        }

        return $manager->hasDirectPermission($requiredPermission);
    }

    /**
     * Get all tenants where the user has the manager role.
     * Used to populate the tenant switcher (BR-487, BR-488).
     *
     * @return Collection<int, object{id: int, name: string, slug: string, domain: string|null, first_letter: string}>
     */
    public function getManagedTenants(User $user): Collection
    {
        $locale = in_array(app()->getLocale(), ['en', 'fr']) ? app()->getLocale() : 'en';
        $nameColumn = 'name_'.$locale;

        return DB::table('tenant_managers')
            ->join('tenants', 'tenants.id', '=', 'tenant_managers.tenant_id')
            ->where('tenant_managers.user_id', $user->id)
            ->selectRaw("tenants.id, tenants.{$nameColumn} as name, tenants.slug, tenants.custom_domain, tenants.is_active")
            ->orderBy("tenants.{$nameColumn}")
            ->get()
            ->map(function ($tenant) {
                $tenant->first_letter = mb_strtoupper(mb_substr($tenant->name ?? '', 0, 1));

                return $tenant;
            });
    }

    /**
     * Get the URL for a tenant's dashboard.
     * Used for tenant switcher links (BR-489).
     */
    public function getTenantDashboardUrl(object $tenantRow): string
    {
        $mainDomain = parse_url(config('app.url'), PHP_URL_HOST);
        $customDomain = property_exists($tenantRow, 'custom_domain')
            ? $tenantRow->custom_domain
            : ($tenantRow->domain ?? null);

        if (! empty($customDomain)) {
            $scheme = request()->isSecure() ? 'https' : 'http';

            return $scheme.'://'.$customDomain.'/dashboard';
        }

        $scheme = request()->isSecure() ? 'https' : 'http';

        return $scheme.'://'.$tenantRow->slug.'.'.$mainDomain.'/dashboard';
    }

    /**
     * Determine whether a manager has any permissions at all.
     * Used for the "no permissions" state (BR-486).
     */
    public function hasAnyPermission(User $manager): bool
    {
        foreach (ManagerPermissionService::DELEGATABLE_PERMISSIONS as $permission) {
            if ($manager->hasDirectPermission($permission)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Log a manager's section access for audit purposes (BR-490).
     *
     * Logs: user ID, tenant, section accessed, timestamp.
     */
    public function logSectionAccess(User $manager, Tenant $tenant, string $section): void
    {
        activity()
            ->causedBy($manager)
            ->performedOn($tenant)
            ->withProperties([
                'section' => $section,
                'manager_id' => $manager->id,
                'manager_email' => $manager->email,
                'tenant_id' => $tenant->id,
            ])
            ->log('manager_section_accessed');
    }

    /**
     * Extract the canonical section name from a request path for logging.
     * e.g. 'dashboard/orders/123' → 'orders'
     */
    public function getSectionName(string $requestPath): string
    {
        $segments = explode('/', ltrim($requestPath, '/'));
        // Remove 'dashboard' prefix
        if (count($segments) > 1 && $segments[0] === 'dashboard') {
            return $segments[1];
        }

        return 'home';
    }
}
