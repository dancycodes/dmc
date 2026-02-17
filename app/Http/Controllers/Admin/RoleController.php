<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreRoleRequest;
use App\Http\Requests\Admin\UpdateRoleRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    /**
     * System role names that cannot be used for custom roles.
     *
     * BR-110: These names are reserved and cannot be reused.
     *
     * @var list<string>
     */
    public const SYSTEM_ROLE_NAMES = [
        'super-admin',
        'admin',
        'cook',
        'manager',
        'client',
    ];

    /**
     * Hierarchy order for system roles.
     *
     * BR-115: System roles sorted in hierarchy order.
     *
     * @var array<string, int>
     */
    private const SYSTEM_ROLE_ORDER = [
        'super-admin' => 1,
        'admin' => 2,
        'cook' => 3,
        'manager' => 4,
        'client' => 5,
    ];

    /**
     * List all roles with permission counts, user counts, and type filtering.
     *
     * F-053: Role List View
     * BR-111: System roles are: super-admin, admin, cook, manager, client
     * BR-112: System roles display a "System" badge
     * BR-113: Permission count reflects total permissions assigned
     * BR-114: User count reflects total users holding this role
     * BR-115: System roles first (hierarchy order), then custom alphabetically
     */
    public function index(Request $request): mixed
    {
        $type = $request->input('type', '');

        // Validate type filter
        if (! in_array($type, ['', 'system', 'custom'], true)) {
            $type = '';
        }

        $query = Role::query()
            ->where('guard_name', 'web')
            ->withCount(['permissions', 'users']);

        // Apply type filter
        if ($type === 'system') {
            $query->where('is_system', true);
        } elseif ($type === 'custom') {
            $query->where('is_system', false);
        }

        $roles = $query->get();

        // BR-115: Sort system roles by hierarchy, custom roles alphabetically
        $roles = $roles->sort(function ($a, $b) {
            // System roles come first
            if ($a->is_system && ! $b->is_system) {
                return -1;
            }
            if (! $a->is_system && $b->is_system) {
                return 1;
            }
            // Both system: sort by hierarchy
            if ($a->is_system && $b->is_system) {
                $orderA = self::SYSTEM_ROLE_ORDER[$a->name] ?? 99;
                $orderB = self::SYSTEM_ROLE_ORDER[$b->name] ?? 99;

                return $orderA <=> $orderB;
            }

            // Both custom: sort alphabetically by name_en
            return strcasecmp($a->name_en ?? $a->name, $b->name_en ?? $b->name);
        })->values();

        // Summary counts
        $totalCount = Role::query()->where('guard_name', 'web')->count();
        $systemCount = Role::query()->where('guard_name', 'web')->where('is_system', true)->count();
        $customCount = Role::query()->where('guard_name', 'web')->where('is_system', false)->count();

        $data = [
            'roles' => $roles,
            'type' => $type,
            'totalCount' => $totalCount,
            'systemCount' => $systemCount,
            'customCount' => $customCount,
        ];

        // Gale navigate pattern for filter tab updates
        if ($request->isGaleNavigate('role-list')) {
            return gale()->fragment('admin.roles.index', 'role-list-content', $data);
        }

        return gale()->view('admin.roles.index', $data, web: true);
    }

    /**
     * Show the role creation form.
     *
     * F-052: Create Role
     * BR-105: Only users with can-access-admin-panel permission (enforced by middleware)
     */
    public function create(Request $request): mixed
    {
        return gale()->view('admin.roles.create', [], web: true);
    }

    /**
     * Store a newly created role.
     *
     * F-052: Create Role
     * BR-104: Role names must be unique across the platform
     * BR-105: Only users with can-manage-roles permission
     * BR-106: Guard defaults to "web"
     * BR-107: Both name_en and name_fr required
     * BR-108: Role creation logged in activity log
     * BR-109: Newly created roles have zero permissions
     * BR-110: System role names cannot be used
     */
    public function store(Request $request): mixed
    {
        if (! $request->user()?->can('can-manage-roles')) {
            abort(403);
        }

        if ($request->isGale()) {
            $validated = $request->validateState($this->storeValidationRules());

            return $this->createRole($validated, $request);
        }

        $formRequest = app(StoreRoleRequest::class);

        return $this->createRole($formRequest->validated(), $request);
    }

    /**
     * Get the validation rules for role creation.
     *
     * @return array<string, array<int, mixed>>
     */
    private function storeValidationRules(): array
    {
        return [
            'name_en' => [
                'required',
                'string',
                'min:2',
                'max:100',
                'regex:/^[a-zA-Z0-9\s-]+$/',
                function (string $attribute, mixed $value, \Closure $fail) {
                    // BR-110: System role names cannot be used
                    $normalized = strtolower(trim($value));
                    $machineName = str_replace(' ', '-', $normalized);
                    if (in_array($machineName, self::SYSTEM_ROLE_NAMES, true)) {
                        $fail(__('This role name is reserved and cannot be used.'));
                    }

                    // BR-104: Check uniqueness of name_en
                    $exists = Role::query()
                        ->where('name_en', trim($value))
                        ->exists();
                    if ($exists) {
                        $fail(__('A role with this name already exists.'));
                    }

                    // Also check against Spatie's name column
                    if (Role::query()->where('name', $machineName)->exists()) {
                        $fail(__('A role with this name already exists.'));
                    }
                },
            ],
            'name_fr' => [
                'required',
                'string',
                'min:2',
                'max:100',
                'regex:/^[a-zA-Z0-9\s\x{00C0}-\x{024F}-]+$/u',
                function (string $attribute, mixed $value, \Closure $fail) {
                    // BR-104: Check uniqueness of name_fr
                    $exists = Role::query()
                        ->where('name_fr', trim($value))
                        ->exists();
                    if ($exists) {
                        $fail(__('A role with this French name already exists.'));
                    }
                },
            ],
            'description' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * Create the role from validated data.
     *
     * BR-106: Guard defaults to "web"
     * BR-108: Activity logging
     * BR-109: Zero permissions on creation
     */
    private function createRole(array $validated, Request $request): mixed
    {
        $nameEn = trim($validated['name_en']);
        $nameFr = trim($validated['name_fr']);
        $description = isset($validated['description']) ? trim($validated['description']) : null;

        // Generate machine-friendly name from English name
        $machineName = strtolower(str_replace(' ', '-', $nameEn));

        // BR-106: Guard defaults to "web"
        $role = Role::create([
            'name' => $machineName,
            'guard_name' => 'web',
            'name_en' => $nameEn,
            'name_fr' => $nameFr,
            'description' => $description !== '' ? $description : null,
            'is_system' => false,
        ]);

        // BR-108: Activity logging
        activity('roles')
            ->performedOn($role)
            ->causedBy($request->user())
            ->withProperties([
                'name' => $role->name,
                'name_en' => $role->name_en,
                'name_fr' => $role->name_fr,
                'description' => $role->description,
                'guard_name' => $role->guard_name,
                'ip' => $request->ip(),
            ])
            ->log('created');

        // Redirect to permission assignment page (F-056)
        session()->flash('toast', [
            'type' => 'success',
            'message' => __('Role ":name" created successfully. You can now assign permissions to this role.', ['name' => $nameEn]),
        ]);

        $permissionsUrl = url('/vault-entry/roles/'.$role->id.'/permissions');

        if ($request->isGale()) {
            return gale()->redirect($permissionsUrl)->back($permissionsUrl);
        }

        return redirect($permissionsUrl);
    }

    /**
     * Show the role edit form.
     *
     * F-054: Edit Role
     * BR-116: System role names are read-only
     * BR-117: System role descriptions can be updated
     * BR-121: Permission modifications redirected to F-056
     */
    public function edit(Request $request, Role $role): mixed
    {
        // Load current permissions grouped by module for read-only display
        $permissions = $role->permissions()
            ->orderBy('name')
            ->get();

        $groupedPermissions = $this->groupPermissionsByModule($permissions);

        return gale()->view('admin.roles.edit', [
            'role' => $role,
            'groupedPermissions' => $groupedPermissions,
        ], web: true);
    }

    /**
     * Update a role's name and description.
     *
     * F-054: Edit Role
     * BR-116: System role names cannot be changed
     * BR-117: System role descriptions can be updated
     * BR-118: Custom role names can be changed but must remain unique
     * BR-119: Role name uniqueness excludes current role
     * BR-120: All edits logged in activity log
     */
    public function update(Request $request, Role $role): mixed
    {
        if (! $request->user()?->can('can-manage-roles')) {
            abort(403);
        }

        if ($request->isGale()) {
            $validated = $request->validateState($this->updateValidationRules($role));

            return $this->updateRole($validated, $role, $request);
        }

        $formRequest = app(UpdateRoleRequest::class);

        return $this->updateRole($formRequest->validated(), $role, $request);
    }

    /**
     * Get the validation rules for role update.
     *
     * @return array<string, array<int, mixed>>
     */
    private function updateValidationRules(Role $role): array
    {
        $rules = [
            'description' => ['nullable', 'string', 'max:500'],
        ];

        // BR-116: System roles cannot have their names changed
        if (! $role->is_system) {
            $rules['name_en'] = [
                'required',
                'string',
                'min:2',
                'max:100',
                'regex:/^[a-zA-Z0-9\s-]+$/',
                function (string $attribute, mixed $value, \Closure $fail) use ($role) {
                    $normalized = strtolower(trim($value));
                    $machineName = str_replace(' ', '-', $normalized);

                    // BR-110: System role names cannot be used
                    if (in_array($machineName, self::SYSTEM_ROLE_NAMES, true)) {
                        $fail(__('This role name is reserved and cannot be used.'));
                    }

                    // BR-119: Check uniqueness excluding current role
                    $exists = Role::query()
                        ->where('name_en', trim($value))
                        ->where('id', '!=', $role->id)
                        ->exists();
                    if ($exists) {
                        $fail(__('A role with this name already exists.'));
                    }

                    // Also check against Spatie's name column (excluding current)
                    if (Role::query()->where('name', $machineName)->where('id', '!=', $role->id)->exists()) {
                        $fail(__('A role with this name already exists.'));
                    }
                },
            ];
            $rules['name_fr'] = [
                'required',
                'string',
                'min:2',
                'max:100',
                'regex:/^[a-zA-Z0-9\s\x{00C0}-\x{024F}-]+$/u',
                function (string $attribute, mixed $value, \Closure $fail) use ($role) {
                    // BR-119: Check uniqueness excluding current role
                    $exists = Role::query()
                        ->where('name_fr', trim($value))
                        ->where('id', '!=', $role->id)
                        ->exists();
                    if ($exists) {
                        $fail(__('A role with this French name already exists.'));
                    }
                },
            ];
        }

        return $rules;
    }

    /**
     * Update the role from validated data.
     *
     * BR-116: System role names are not changed
     * BR-120: Activity logging with old/new comparison
     */
    private function updateRole(array $validated, Role $role, Request $request): mixed
    {
        $description = isset($validated['description']) ? trim($validated['description']) : null;
        $description = $description !== '' ? $description : null;

        $oldValues = [
            'name_en' => $role->name_en,
            'name_fr' => $role->name_fr,
            'name' => $role->name,
            'description' => $role->description,
        ];

        $hasChanges = false;

        // BR-116: Only update names for custom roles
        if (! $role->is_system) {
            $nameEn = trim($validated['name_en']);
            $nameFr = trim($validated['name_fr']);
            $machineName = strtolower(str_replace(' ', '-', $nameEn));

            if ($role->name_en !== $nameEn || $role->name_fr !== $nameFr || $role->name !== $machineName) {
                $hasChanges = true;
            }

            $role->name_en = $nameEn;
            $role->name_fr = $nameFr;
            $role->name = $machineName;
        }

        if ($role->description !== $description) {
            $hasChanges = true;
        }
        $role->description = $description;

        $role->save();

        // BR-120: Activity logging only when changes were made
        if ($hasChanges) {
            $newValues = [
                'name_en' => $role->name_en,
                'name_fr' => $role->name_fr,
                'name' => $role->name,
                'description' => $role->description,
            ];

            activity('roles')
                ->performedOn($role)
                ->causedBy($request->user())
                ->withProperties([
                    'old' => $oldValues,
                    'new' => $newValues,
                    'ip' => $request->ip(),
                ])
                ->log('updated');
        }

        // Redirect to roles list with success toast
        session()->flash('toast', [
            'type' => 'success',
            'message' => __('Role ":name" updated successfully.', ['name' => $role->name_en]),
        ]);

        if ($request->isGale()) {
            return gale()->redirect(url('/vault-entry/roles'))->back('/vault-entry/roles');
        }

        return redirect('/vault-entry/roles');
    }

    /**
     * Show the permission assignment page for a role.
     *
     * F-056: Permission Assignment to Roles
     * BR-128: Super-admin role always has ALL permissions — read-only view
     * BR-129: Admin can only assign permissions they themselves possess
     * BR-130: Permissions the admin does not have are visible but disabled
     * BR-134: Permissions grouped by module for organized display
     */
    public function permissions(Request $request, Role $role): mixed
    {
        $user = $request->user();

        // Load all platform permissions
        $allPermissions = Permission::query()
            ->where('guard_name', 'web')
            ->orderBy('name')
            ->get();

        // Get current role's permission names
        $rolePermissionNames = $role->permissions()->pluck('name')->toArray();

        // BR-129 / BR-130: Get admin's own permissions for escalation prevention
        $isSuperAdmin = $user->hasRole('super-admin');
        $adminPermissionNames = $isSuperAdmin
            ? $allPermissions->pluck('name')->toArray()
            : $user->getAllPermissions()->pluck('name')->toArray();

        // BR-128: Super-admin role is always read-only
        $isReadOnly = $role->name === 'super-admin';

        // Group permissions by module
        $groupedPermissions = $this->groupPermissionsForAssignment($allPermissions, $rolePermissionNames, $adminPermissionNames);

        $data = [
            'role' => $role,
            'groupedPermissions' => $groupedPermissions,
            'rolePermissionNames' => $rolePermissionNames,
            'adminPermissionNames' => $adminPermissionNames,
            'isReadOnly' => $isReadOnly,
            'isSuperAdmin' => $isSuperAdmin,
            'totalPermissions' => $allPermissions->count(),
            'assignedCount' => count($rolePermissionNames),
        ];

        return gale()->view('admin.roles.permissions', $data, web: true);
    }

    /**
     * Toggle a single permission on a role.
     *
     * F-056: Permission Assignment to Roles
     * BR-128: Cannot modify super-admin permissions
     * BR-129: Cannot assign permissions the admin doesn't have
     * BR-131: Changes save immediately via Gale
     * BR-132: Takes effect on next request
     * BR-133: Changes logged in activity log
     */
    public function togglePermission(Request $request, Role $role): mixed
    {
        if (! $request->user()?->can('can-manage-roles')) {
            abort(403);
        }

        $user = $request->user();

        // BR-128: Cannot modify super-admin permissions
        if ($role->name === 'super-admin') {
            if ($request->isGale()) {
                return gale()->state('error', __('Super-admin permissions cannot be modified.'));
            }
            abort(403);
        }

        // Validate the permission name
        if ($request->isGale()) {
            $validated = $request->validateState([
                'permission' => ['required', 'string', 'exists:permissions,name'],
            ]);
        } else {
            $validated = $request->validate([
                'permission' => ['required', 'string', 'exists:permissions,name'],
            ]);
        }

        $permissionName = $validated['permission'];

        // BR-129: Cannot assign permissions the admin doesn't have
        $isSuperAdmin = $user->hasRole('super-admin');
        if (! $isSuperAdmin && ! $user->can($permissionName)) {
            if ($request->isGale()) {
                return gale()->state('error', __('You cannot assign permissions you do not have.'));
            }
            abort(403);
        }

        // Toggle the permission
        $hasPermission = $role->hasPermissionTo($permissionName);

        if ($hasPermission) {
            $role->revokePermissionTo($permissionName);
            $action = 'revoked';
        } else {
            $role->givePermissionTo($permissionName);
            $action = 'granted';
        }

        // BR-133: Activity logging
        activity('roles')
            ->performedOn($role)
            ->causedBy($user)
            ->withProperties([
                'permission' => $permissionName,
                'action' => $action,
                'ip' => $request->ip(),
            ])
            ->log("permission_{$action}");

        // Recalculate assigned count
        $assignedCount = $role->permissions()->count();

        if ($request->isGale()) {
            return gale()->state([
                'assignedCount' => $assignedCount,
                'lastToggled' => $permissionName,
                'lastAction' => $action,
                'error' => '',
            ]);
        }

        session()->flash('toast', [
            'type' => 'success',
            'message' => $action === 'granted'
                ? __('Permission ":name" granted.', ['name' => $permissionName])
                : __('Permission ":name" revoked.', ['name' => $permissionName]),
        ]);

        return redirect()->back();
    }

    /**
     * Toggle all permissions in a module for a role.
     *
     * F-056: Permission Assignment to Roles (Scenario 2: Bulk selecting a module)
     * BR-128: Cannot modify super-admin permissions
     * BR-129: Cannot assign permissions the admin doesn't have
     * BR-131: Changes save immediately via Gale
     * BR-133: Changes logged in activity log
     */
    public function toggleModule(Request $request, Role $role): mixed
    {
        if (! $request->user()?->can('can-manage-roles')) {
            abort(403);
        }

        $user = $request->user();

        // BR-128: Cannot modify super-admin permissions
        if ($role->name === 'super-admin') {
            if ($request->isGale()) {
                return gale()->state('error', __('Super-admin permissions cannot be modified.'));
            }
            abort(403);
        }

        if ($request->isGale()) {
            $validated = $request->validateState([
                'togglePermissions' => ['required', 'array'],
                'togglePermissions.*' => ['required', 'string', 'exists:permissions,name'],
                'grant' => ['required', 'boolean'],
            ]);
        } else {
            $validated = $request->validate([
                'permissions' => ['required', 'array'],
                'permissions.*' => ['required', 'string', 'exists:permissions,name'],
                'grant' => ['required', 'boolean'],
            ]);
        }

        $permissionNames = $validated['togglePermissions'] ?? $validated['permissions'];
        $shouldGrant = $validated['grant'];

        // BR-129: Filter out permissions the admin doesn't have (non-super-admins)
        $isSuperAdmin = $user->hasRole('super-admin');
        if (! $isSuperAdmin) {
            $permissionNames = array_filter($permissionNames, fn (string $p) => $user->can($p));
        }

        $granted = [];
        $revoked = [];

        foreach ($permissionNames as $permissionName) {
            $hasPermission = $role->hasPermissionTo($permissionName);

            if ($shouldGrant && ! $hasPermission) {
                $role->givePermissionTo($permissionName);
                $granted[] = $permissionName;
            } elseif (! $shouldGrant && $hasPermission) {
                $role->revokePermissionTo($permissionName);
                $revoked[] = $permissionName;
            }
        }

        // BR-133: Activity logging for bulk changes
        if (count($granted) > 0 || count($revoked) > 0) {
            activity('roles')
                ->performedOn($role)
                ->causedBy($user)
                ->withProperties([
                    'action' => $shouldGrant ? 'module_granted' : 'module_revoked',
                    'granted' => $granted,
                    'revoked' => $revoked,
                    'ip' => $request->ip(),
                ])
                ->log($shouldGrant ? 'permissions_granted' : 'permissions_revoked');
        }

        // Recalculate assigned count
        $assignedCount = $role->permissions()->count();

        if ($request->isGale()) {
            return gale()->state([
                'assignedCount' => $assignedCount,
                'error' => '',
            ]);
        }

        session()->flash('toast', [
            'type' => 'success',
            'message' => $shouldGrant
                ? __(':count permissions granted.', ['count' => count($granted)])
                : __(':count permissions revoked.', ['count' => count($revoked)]),
        ]);

        return redirect()->back();
    }

    /**
     * Group all permissions for the assignment page.
     *
     * Returns a structured array with module name, permissions, assigned state, and admin capability.
     *
     * @return array<string, array{permissions: list<array{name: string, assigned: bool, canAssign: bool, verb: string}>, assignedCount: int, totalCount: int}>
     */
    private function groupPermissionsForAssignment(
        \Illuminate\Support\Collection $allPermissions,
        array $rolePermissionNames,
        array $adminPermissionNames,
    ): array {
        $grouped = [];

        foreach ($allPermissions as $permission) {
            $name = $permission->name;
            $parts = explode('-', $name);

            // Remove 'can' prefix
            if ($parts[0] === 'can') {
                array_shift($parts);
            }

            // Extract verb and module
            if (count($parts) >= 2) {
                $verb = $parts[0];
                $noun = implode(' ', array_slice($parts, 1));
                $module = ucfirst($noun);
            } else {
                $verb = $parts[0] ?? $name;
                $module = __('General');
            }

            if (! isset($grouped[$module])) {
                $grouped[$module] = [
                    'permissions' => [],
                    'assignedCount' => 0,
                    'totalCount' => 0,
                ];
            }

            $isAssigned = in_array($name, $rolePermissionNames, true);
            $canAssign = in_array($name, $adminPermissionNames, true);

            $grouped[$module]['permissions'][] = [
                'name' => $name,
                'assigned' => $isAssigned,
                'canAssign' => $canAssign,
                'verb' => $verb,
            ];

            $grouped[$module]['totalCount']++;
            if ($isAssigned) {
                $grouped[$module]['assignedCount']++;
            }
        }

        // Sort modules alphabetically
        ksort($grouped);

        return $grouped;
    }

    /**
     * Group permissions by module for display.
     *
     * Extracts the module from permission names like "can-manage-meals" -> "Meals".
     *
     * @return array<string, list<string>>
     */
    private function groupPermissionsByModule(\Illuminate\Support\Collection $permissions): array
    {
        $grouped = [];

        foreach ($permissions as $permission) {
            // Parse "can-verb-noun" format
            $name = $permission->name;
            $parts = explode('-', $name);

            // Remove 'can' prefix
            if ($parts[0] === 'can') {
                array_shift($parts);
            }

            // Extract verb and noun
            if (count($parts) >= 2) {
                $verb = $parts[0];
                $noun = implode(' ', array_slice($parts, 1));
                $module = ucfirst($noun);
            } else {
                $verb = $parts[0] ?? $name;
                $module = __('General');
            }

            if (! isset($grouped[$module])) {
                $grouped[$module] = [];
            }
            $grouped[$module][] = $verb;
        }

        // Sort modules alphabetically
        ksort($grouped);

        return $grouped;
    }

    /**
     * Delete a custom role.
     *
     * F-055: Delete Role
     * BR-122: System roles cannot be deleted
     * BR-123: Custom roles with assigned users cannot be deleted
     * BR-124: Deletion is permanent — role and permission assignments removed
     * BR-125: Deletion requires explicit confirmation (role name typed)
     * BR-126: Deletion is logged in the activity log
     * BR-127: After deletion, redirect to role list
     */
    public function destroy(Request $request, Role $role): mixed
    {
        if (! $request->user()?->can('can-manage-roles')) {
            abort(403);
        }

        // BR-122: System roles cannot be deleted
        if ($role->is_system) {
            if ($request->isGale()) {
                return gale()->state('deleteError', __('System roles cannot be deleted.'));
            }
            abort(403, __('System roles cannot be deleted.'));
        }

        // BR-123: Roles with assigned users cannot be deleted
        $userCount = $role->users()->count();
        if ($userCount > 0) {
            $message = __('Cannot delete this role. :count users are currently assigned to it. Reassign them first.', ['count' => $userCount]);
            if ($request->isGale()) {
                return gale()->state('deleteError', $message);
            }
            session()->flash('toast', [
                'type' => 'error',
                'message' => $message,
            ]);

            return redirect('/vault-entry/roles');
        }

        // BR-125: Validate confirmation name matches role name
        if ($request->isGale()) {
            $validated = $request->validateState([
                'confirmRoleName' => [
                    'required',
                    'string',
                    function (string $attribute, mixed $value, \Closure $fail) use ($role) {
                        if (trim($value) !== ($role->name_en ?? $role->name)) {
                            $fail(__('The role name does not match. Please type the exact role name to confirm deletion.'));
                        }
                    },
                ],
            ]);
        } else {
            $request->validate([
                'confirmRoleName' => [
                    'required',
                    'string',
                    function (string $attribute, mixed $value, \Closure $fail) use ($role) {
                        if (trim($value) !== ($role->name_en ?? $role->name)) {
                            $fail(__('The role name does not match. Please type the exact role name to confirm deletion.'));
                        }
                    },
                ],
            ]);
        }

        // Capture role info before deletion for logging
        $roleData = [
            'name' => $role->name,
            'name_en' => $role->name_en,
            'name_fr' => $role->name_fr,
            'description' => $role->description,
            'guard_name' => $role->guard_name,
            'permissions_count' => $role->permissions()->count(),
            'ip' => $request->ip(),
        ];

        $roleNameEn = $role->name_en ?? $role->name;

        // BR-124: Permanent deletion within a transaction
        DB::transaction(function () use ($role, $roleData, $request) {
            // BR-126: Activity logging before deletion
            activity('roles')
                ->performedOn($role)
                ->causedBy($request->user())
                ->withProperties($roleData)
                ->log('deleted');

            // Remove all permission assignments and delete the role
            $role->syncPermissions([]);
            $role->delete();
        });

        // BR-127: Redirect to role list with success toast
        session()->flash('toast', [
            'type' => 'success',
            'message' => __('Role ":name" has been deleted.', ['name' => $roleNameEn]),
        ]);

        if ($request->isGale()) {
            return gale()->redirect(url('/vault-entry/roles'))->back('/vault-entry/roles');
        }

        return redirect('/vault-entry/roles');
    }
}
