<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreRoleRequest;
use Illuminate\Http\Request;
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

        // Redirect to roles list with success toast
        // F-056 (Permission Assignment) is not yet built, so redirect to role list
        // When F-056 is built, this should redirect to permission assignment page
        session()->flash('toast', [
            'type' => 'success',
            'message' => __('Role ":name" created successfully. You can now assign permissions to this role.', ['name' => $nameEn]),
        ]);

        if ($request->isGale()) {
            return gale()->redirect(url('/vault-entry/roles'))->back('/vault-entry/roles');
        }

        return redirect('/vault-entry/roles');
    }
}
