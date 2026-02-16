<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    /**
     * List all platform users with search, filter, sort, and pagination.
     *
     * F-050: User Management List & Search
     * BR-089: User list shows ALL platform users regardless of tenant
     * BR-090: Pagination defaults to 20 items per page
     * BR-091: Search covers: name, email, phone number
     * BR-092: Role filter shows all system roles
     * BR-093: Status filter options: All, Active, Inactive
     * BR-094: Default sort: registration date descending (newest first)
     * BR-095: Last login shows relative time
     * BR-096: Users with multiple roles show all roles as separate badges
     */
    public function index(Request $request): mixed
    {
        $search = $request->input('search', '');
        $role = $request->input('role', '');
        $status = $request->input('status', '');
        $sortBy = $request->input('sort', 'created_at');
        $sortDir = $request->input('direction', 'desc');

        // Validate sort column to prevent SQL injection
        $allowedSorts = ['name', 'email', 'phone', 'created_at', 'last_login_at', 'status'];
        if (! in_array($sortBy, $allowedSorts, true)) {
            $sortBy = 'created_at';
        }
        $sortDir = in_array($sortDir, ['asc', 'desc'], true) ? $sortDir : 'desc';

        // Map friendly sort names to actual columns
        $sortColumn = match ($sortBy) {
            'status' => 'is_active',
            default => $sortBy,
        };

        $query = User::query()->with('roles');

        // BR-091: Search covers name, email, phone
        if ($search !== '') {
            $searchTerm = '%'.$search.'%';

            // Normalize phone search: strip +237 / 237 prefix
            $phoneSearch = preg_replace('/[\s\-()]/', '', $search);
            if (str_starts_with($phoneSearch, '+237')) {
                $phoneSearch = substr($phoneSearch, 4);
            } elseif (str_starts_with($phoneSearch, '237') && strlen($phoneSearch) >= 12) {
                $phoneSearch = substr($phoneSearch, 3);
            }
            $phoneSearchTerm = '%'.$phoneSearch.'%';

            $query->where(function ($q) use ($searchTerm, $phoneSearchTerm) {
                $q->where('name', 'ilike', $searchTerm)
                    ->orWhere('email', 'ilike', $searchTerm)
                    ->orWhere('phone', 'ilike', $phoneSearchTerm);
            });
        }

        // BR-092: Filter by role
        if ($role !== '') {
            $query->whereHas('roles', function ($q) use ($role) {
                $q->where('name', $role);
            });
        }

        // BR-093: Filter by status
        if ($status === 'active') {
            $query->where('is_active', true);
        } elseif ($status === 'inactive') {
            $query->where('is_active', false);
        }

        // BR-094: Sort
        $query->orderBy($sortColumn, $sortDir);

        // BR-090: 20 items per page
        $users = $query->paginate(20)->withQueryString();

        // Summary counts
        $totalCount = User::count();
        $activeCount = User::where('is_active', true)->count();
        $inactiveCount = User::where('is_active', false)->count();
        $newThisMonth = User::where('created_at', '>=', now()->startOfMonth())->count();

        // BR-092: All available roles for filter dropdown
        $allRoles = Role::orderBy('name')->pluck('name');

        $data = [
            'users' => $users,
            'search' => $search,
            'role' => $role,
            'status' => $status,
            'sortBy' => $sortBy,
            'sortDir' => $sortDir,
            'totalCount' => $totalCount,
            'activeCount' => $activeCount,
            'inactiveCount' => $inactiveCount,
            'newThisMonth' => $newThisMonth,
            'allRoles' => $allRoles,
        ];

        // Handle Gale navigate requests (search/filter/sort triggers)
        if ($request->isGaleNavigate('user-list')) {
            return gale()->fragment('admin.users.index', 'user-list-content', $data);
        }

        return gale()->view('admin.users.index', $data, web: true);
    }

    /**
     * Display comprehensive detail page for a single user.
     *
     * F-051: User Detail View & Status Toggle
     * BR-097: Deactivating a user invalidates all their active sessions immediately
     * BR-099: Admins cannot deactivate super-admin accounts (only other super-admins can)
     * BR-100: Admins cannot deactivate their own account from the admin panel
     * BR-101: Reactivation allows the user to log in again; no data is lost
     * BR-102: Status changes are recorded in the activity log with the admin as causer
     * BR-103: Wallet balance is displayed but cannot be modified from this page
     */
    public function show(Request $request, User $user): mixed
    {
        $user->load('roles');

        $admin = $request->user();

        // Determine if current admin can toggle this user's status
        $canToggleStatus = $this->canToggleUserStatus($admin, $user);
        $toggleDisabledReason = $this->getToggleDisabledReason($admin, $user);

        // Get user roles with associated tenants
        $userRolesWithTenants = $this->getUserRolesWithTenants($user);

        // Order summary (stubbed — no orders table yet)
        $clientOrderCount = 0;
        $clientTotalSpent = 0;
        $isCook = $user->hasRole('cook');
        $cookOrderCount = 0;
        $cookRevenue = 0;

        // Wallet balance (stubbed — no wallet table yet)
        $walletBalance = null;

        // Activity log: entries where this user is causer or subject
        $activityPage = $request->input('activity_page', 1);
        $activities = Activity::query()
            ->where(function ($q) use ($user) {
                $q->where(function ($q2) use ($user) {
                    $q2->where('causer_type', User::class)
                        ->where('causer_id', $user->id);
                })->orWhere(function ($q2) use ($user) {
                    $q2->where('subject_type', User::class)
                        ->where('subject_id', $user->id);
                });
            })
            ->with('causer')
            ->orderByDesc('created_at')
            ->paginate(10, ['*'], 'activity_page', $activityPage)
            ->withQueryString();

        $data = [
            'user' => $user,
            'canToggleStatus' => $canToggleStatus,
            'toggleDisabledReason' => $toggleDisabledReason,
            'userRolesWithTenants' => $userRolesWithTenants,
            'clientOrderCount' => $clientOrderCount,
            'clientTotalSpent' => $clientTotalSpent,
            'isCook' => $isCook,
            'cookOrderCount' => $cookOrderCount,
            'cookRevenue' => $cookRevenue,
            'walletBalance' => $walletBalance,
            'activities' => $activities,
        ];

        // Handle Gale navigate requests for activity log pagination
        if ($request->isGaleNavigate('activity-log')) {
            return gale()->fragment('admin.users.show', 'user-activity-log', $data);
        }

        return gale()->view('admin.users.show', $data, web: true);
    }

    /**
     * Toggle the user's active/inactive status.
     *
     * F-051: User Detail View & Status Toggle
     * BR-097: Deactivating a user invalidates all their active sessions immediately
     * BR-098: Deactivated users cannot log in
     * BR-099: Admins cannot deactivate super-admin accounts (only other super-admins can)
     * BR-100: Admins cannot deactivate their own account from the admin panel
     * BR-101: Reactivation allows the user to log in again; no data is lost
     * BR-102: Status changes are recorded in the activity log with the admin as causer
     */
    public function toggleStatus(Request $request, User $user): mixed
    {
        $admin = $request->user();

        // BR-099, BR-100: Check permission to toggle
        if (! $this->canToggleUserStatus($admin, $user)) {
            abort(403);
        }

        $oldStatus = $user->is_active;
        $newStatus = ! $oldStatus;

        $user->update(['is_active' => $newStatus]);

        // BR-097: If deactivating, invalidate all active sessions
        if (! $newStatus) {
            DB::table('sessions')
                ->where('user_id', $user->id)
                ->delete();
        }

        // BR-102: Log status change with admin as causer
        activity('users')
            ->performedOn($user)
            ->causedBy($admin)
            ->withProperties([
                'old' => ['is_active' => $oldStatus],
                'attributes' => ['is_active' => $newStatus],
                'ip' => $request->ip(),
            ])
            ->log($newStatus ? 'activated' : 'deactivated');

        $statusLabel = $newStatus ? __('activated') : __('deactivated');

        session()->flash('toast', [
            'type' => 'success',
            'message' => __('User ":name" has been :status.', [
                'name' => $user->name,
                'status' => $statusLabel,
            ]),
        ]);

        if ($request->isGale()) {
            return gale()->redirect(url('/vault-entry/users/'.$user->id))->back('/vault-entry/users/'.$user->id);
        }

        return redirect('/vault-entry/users/'.$user->id);
    }

    /**
     * Check if the current admin can toggle the target user's status.
     *
     * BR-099: Admins cannot deactivate super-admin accounts (only other super-admins can)
     * BR-100: Admins cannot deactivate their own account
     */
    private function canToggleUserStatus(User $admin, User $targetUser): bool
    {
        // BR-100: Cannot toggle your own account
        if ($admin->id === $targetUser->id) {
            return false;
        }

        // BR-099: Only super-admins can toggle other super-admins
        if ($targetUser->hasRole('super-admin') && ! $admin->hasRole('super-admin')) {
            return false;
        }

        return true;
    }

    /**
     * Get the reason why the toggle is disabled.
     */
    private function getToggleDisabledReason(User $admin, User $targetUser): ?string
    {
        if ($admin->id === $targetUser->id) {
            return __('You cannot deactivate your own account');
        }

        if ($targetUser->hasRole('super-admin') && ! $admin->hasRole('super-admin')) {
            return __('Only super-admins can change the status of other super-admins');
        }

        return null;
    }

    /**
     * Get user roles with their associated tenant information.
     *
     * For cook roles, find which tenants they are assigned to.
     * For manager roles, find which tenants they manage.
     *
     * @return array<int, array{role: string, tenant: ?Tenant, role_class: string}>
     */
    private function getUserRolesWithTenants(User $user): array
    {
        $rolesWithTenants = [];

        foreach ($user->roles as $role) {
            $roleData = [
                'role' => $role->name,
                'tenant' => null,
                'role_class' => match ($role->name) {
                    'super-admin' => 'bg-danger-subtle text-danger',
                    'admin' => 'bg-warning-subtle text-warning',
                    'cook' => 'bg-info-subtle text-info',
                    'manager' => 'bg-secondary-subtle text-secondary',
                    'client' => 'bg-success-subtle text-success',
                    default => 'bg-outline/20 text-on-surface/60',
                },
            ];

            // For cook role, find associated tenants
            if ($role->name === 'cook') {
                $cookTenants = Tenant::where('cook_id', $user->id)->get();
                if ($cookTenants->isNotEmpty()) {
                    foreach ($cookTenants as $tenant) {
                        $rolesWithTenants[] = array_merge($roleData, ['tenant' => $tenant]);
                    }

                    continue;
                }
            }

            $rolesWithTenants[] = $roleData;
        }

        return $rolesWithTenants;
    }
}
