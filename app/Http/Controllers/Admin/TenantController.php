<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreTenantRequest;
use App\Http\Requests\Admin\UpdateTenantRequest;
use App\Models\Tenant;
use App\Services\TenantService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Spatie\Activitylog\Models\Activity;

class TenantController extends Controller
{
    /**
     * List all tenants with search, filter, sort, and pagination.
     *
     * F-046: Tenant List & Search View
     * BR-064: Paginated with 15 items per page
     * BR-065: Search covers name_en, name_fr, subdomain, custom_domain
     * BR-066: Status filter: All, Active, Inactive
     * BR-067: Default sort: created_at descending
     * BR-068: All columns are sortable
     * BR-069: Order count aggregate (stubbed until orders table exists)
     */
    public function index(Request $request): mixed
    {
        $search = $request->input('search', '');
        $status = $request->input('status', '');
        $sortBy = $request->input('sort', 'created_at');
        $sortDir = $request->input('direction', 'desc');

        // Validate sort column to prevent SQL injection
        $allowedSorts = ['name', 'slug', 'custom_domain', 'status', 'created_at'];
        if (! in_array($sortBy, $allowedSorts, true)) {
            $sortBy = 'created_at';
        }
        $sortDir = in_array($sortDir, ['asc', 'desc'], true) ? $sortDir : 'desc';

        // Map friendly sort names to actual columns
        $sortColumn = match ($sortBy) {
            'name' => 'name_'.app()->getLocale(),
            'status' => 'is_active',
            default => $sortBy,
        };

        $query = Tenant::query()
            ->search($search)
            ->status($status)
            ->orderBy($sortColumn, $sortDir);

        $tenants = $query->paginate(15)->withQueryString();

        // Summary counts (BR-066)
        $totalCount = Tenant::count();
        $activeCount = Tenant::where('is_active', true)->count();
        $inactiveCount = Tenant::where('is_active', false)->count();

        $data = [
            'tenants' => $tenants,
            'mainDomain' => TenantService::mainDomain(),
            'search' => $search,
            'status' => $status,
            'sortBy' => $sortBy,
            'sortDir' => $sortDir,
            'totalCount' => $totalCount,
            'activeCount' => $activeCount,
            'inactiveCount' => $inactiveCount,
        ];

        // Handle Gale navigate requests (search/filter/sort triggers)
        if ($request->isGaleNavigate('tenant-list')) {
            return gale()->fragment('admin.tenants.index', 'tenant-list-content', $data);
        }

        return gale()->view('admin.tenants.index', $data, web: true);
    }

    /**
     * Show the tenant detail page.
     *
     * F-047: Tenant Detail View
     * BR-070: Total revenue = sum of completed orders (stubbed until orders exist)
     * BR-071: Active meals count = meals with status "available" (stubbed until meals exist)
     * BR-072: Activity history scoped to this tenant
     * BR-073: Commission rate from tenant settings, default 10%
     * BR-074: Visit Site link opens tenant subdomain in new tab
     */
    public function show(Request $request, Tenant $tenant): mixed
    {
        $mainDomain = TenantService::mainDomain();

        // BR-073: Commission rate from settings, default 10%
        $commissionRate = $tenant->getSetting('commission_rate', 10);

        // BR-070: Total revenue and order count (stubbed — orders table not yet created)
        $totalOrders = 0;
        $totalRevenue = 0;

        // BR-071: Active meals count (stubbed — meals table not yet created)
        $activeMeals = 0;

        // F-049: Load the assigned cook
        $tenant->load('cook');
        $cook = $tenant->cook;

        // BR-072: Activity history scoped to this tenant, paginated
        $activityPage = $request->input('activity_page', 1);
        $activities = Activity::query()
            ->where('subject_type', Tenant::class)
            ->where('subject_id', $tenant->id)
            ->with('causer')
            ->orderByDesc('created_at')
            ->paginate(10, ['*'], 'activity_page', $activityPage);

        $data = [
            'tenant' => $tenant,
            'mainDomain' => $mainDomain,
            'commissionRate' => $commissionRate,
            'totalOrders' => $totalOrders,
            'totalRevenue' => $totalRevenue,
            'activeMeals' => $activeMeals,
            'cook' => $cook,
            'activities' => $activities,
        ];

        // Handle Gale navigate for activity history pagination
        if ($request->isGaleNavigate('activity-history')) {
            return gale()->fragment('admin.tenants.show', 'activity-history-content', $data);
        }

        return gale()->view('admin.tenants.show', $data, web: true);
    }

    /**
     * Show the tenant creation form.
     *
     * F-045: Tenant Creation Form
     */
    public function create(Request $request): mixed
    {
        return gale()->view('admin.tenants.create', [
            'mainDomain' => TenantService::mainDomain(),
        ], web: true);
    }

    /**
     * Store a newly created tenant.
     *
     * F-045: Tenant Creation Form
     * BR-056 through BR-063: Validation via validateState (Gale) or StoreTenantRequest (HTTP)
     * BR-062: Tenant creation logged in activity log (via LogsActivityTrait)
     */
    public function store(Request $request): mixed
    {
        // Authorization check (matches StoreTenantRequest authorize())
        if (! $request->user()?->can('can-create-tenant')) {
            abort(403);
        }

        // Dual Gale/HTTP validation pattern
        if ($request->isGale()) {
            $validated = $request->validateState($this->storeValidationRules());

            return $this->createTenant($validated, $request);
        }

        // Traditional HTTP: use StoreTenantRequest validation via manual resolution
        $formRequest = app(StoreTenantRequest::class);

        return $this->createTenant($formRequest->validated(), $request);
    }

    /**
     * Show the tenant edit form.
     *
     * F-048: Tenant Edit & Status Toggle
     */
    public function edit(Request $request, Tenant $tenant): mixed
    {
        if (! $request->user()?->can('can-edit-tenant')) {
            abort(403);
        }

        return gale()->view('admin.tenants.edit', [
            'tenant' => $tenant,
            'mainDomain' => TenantService::mainDomain(),
        ], web: true);
    }

    /**
     * Update the tenant details.
     *
     * F-048: Tenant Edit & Status Toggle
     * BR-078: Subdomain changes follow same validation rules as creation
     * BR-079: Uniqueness checks exclude current tenant's own values
     * BR-080: All edits recorded in activity log with admin as causer
     */
    public function update(Request $request, Tenant $tenant): mixed
    {
        if (! $request->user()?->can('can-edit-tenant')) {
            abort(403);
        }

        if ($request->isGale()) {
            $validated = $request->validateState($this->updateValidationRules($tenant));

            return $this->updateTenant($validated, $tenant, $request);
        }

        $formRequest = app(UpdateTenantRequest::class);

        return $this->updateTenant($formRequest->validated(), $tenant, $request);
    }

    /**
     * Toggle the tenant's active status.
     *
     * F-048: Tenant Edit & Status Toggle
     * BR-075: Deactivated tenant shows "temporarily unavailable" page
     * BR-076: Deactivation does not delete any data
     * BR-080: Status changes recorded in activity log
     * BR-081: Deactivation requires explicit confirmation (handled client-side)
     */
    public function toggleStatus(Request $request, Tenant $tenant): mixed
    {
        if (! $request->user()?->can('can-edit-tenant')) {
            abort(403);
        }

        $oldStatus = $tenant->is_active;
        $newStatus = ! $oldStatus;

        $tenant->update(['is_active' => $newStatus]);

        // BR-080: Log status change with admin as causer
        activity('tenants')
            ->performedOn($tenant)
            ->causedBy($request->user())
            ->withProperties([
                'old' => ['is_active' => $oldStatus],
                'attributes' => ['is_active' => $newStatus],
                'ip' => $request->ip(),
            ])
            ->log($newStatus ? 'activated' : 'deactivated');

        $statusLabel = $newStatus ? __('activated') : __('deactivated');

        session()->flash('toast', [
            'type' => 'success',
            'message' => __('Tenant ":name" has been :status.', [
                'name' => $tenant->name,
                'status' => $statusLabel,
            ]),
        ]);

        if ($request->isGale()) {
            return gale()->redirect(url('/vault-entry/tenants/'.$tenant->slug))->back('/vault-entry/tenants/'.$tenant->slug);
        }

        return redirect('/vault-entry/tenants/'.$tenant->slug);
    }

    /**
     * Get the shared validation rules for store (Gale path).
     *
     * @return array<string, array<int, mixed>>
     */
    private function storeValidationRules(): array
    {
        return [
            'name_en' => ['required', 'string', 'min:1', 'max:255'],
            'name_fr' => ['required', 'string', 'min:1', 'max:255'],
            'subdomain' => [
                'required',
                'string',
                'min:3',
                'max:63',
                'regex:/^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$/',
                'not_regex:/--/',
                'unique:tenants,slug',
                function (string $attribute, mixed $value, \Closure $fail) {
                    if (Tenant::isReservedSubdomain($value)) {
                        $fail(__('This subdomain is reserved and cannot be used.'));
                    }
                },
            ],
            'custom_domain' => [
                'nullable',
                'string',
                'max:255',
                'regex:/^[a-z0-9](?:[a-z0-9.-]*[a-z0-9])?\.[a-z]{2,}$/i',
                'unique:tenants,custom_domain',
                function (string $attribute, mixed $value, \Closure $fail) {
                    if ($value === null || $value === '') {
                        return;
                    }

                    $mainDomain = TenantService::mainDomain();
                    $normalizedValue = strtolower(trim($value));

                    if ($normalizedValue === strtolower($mainDomain)) {
                        $fail(__('This domain conflicts with the platform domain.'));
                    }

                    if (str_ends_with($normalizedValue, '.'.strtolower($mainDomain))) {
                        $fail(__('Use the subdomain field for subdomains of the platform domain.'));
                    }
                },
            ],
            'description_en' => ['required', 'string', 'max:5000'],
            'description_fr' => ['required', 'string', 'max:5000'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Get validation rules for update that exclude current tenant from uniqueness checks.
     *
     * BR-079: Uniqueness checks exclude current tenant's own values
     *
     * @return array<string, array<int, mixed>>
     */
    private function updateValidationRules(Tenant $tenant): array
    {
        return [
            'name_en' => ['required', 'string', 'min:1', 'max:255'],
            'name_fr' => ['required', 'string', 'min:1', 'max:255'],
            'subdomain' => [
                'required',
                'string',
                'min:3',
                'max:63',
                'regex:/^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$/',
                'not_regex:/--/',
                Rule::unique('tenants', 'slug')->ignore($tenant->id),
                function (string $attribute, mixed $value, \Closure $fail) {
                    if (Tenant::isReservedSubdomain($value)) {
                        $fail(__('This subdomain is reserved and cannot be used.'));
                    }
                },
            ],
            'custom_domain' => [
                'nullable',
                'string',
                'max:255',
                'regex:/^[a-z0-9](?:[a-z0-9.-]*[a-z0-9])?\.[a-z]{2,}$/i',
                Rule::unique('tenants', 'custom_domain')->ignore($tenant->id),
                function (string $attribute, mixed $value, \Closure $fail) {
                    if ($value === null || $value === '') {
                        return;
                    }

                    $mainDomain = TenantService::mainDomain();
                    $normalizedValue = strtolower(trim($value));

                    if ($normalizedValue === strtolower($mainDomain)) {
                        $fail(__('This domain conflicts with the platform domain.'));
                    }

                    if (str_ends_with($normalizedValue, '.'.strtolower($mainDomain))) {
                        $fail(__('Use the subdomain field for subdomains of the platform domain.'));
                    }
                },
            ],
            'description_en' => ['required', 'string', 'max:5000'],
            'description_fr' => ['required', 'string', 'max:5000'],
        ];
    }

    /**
     * Create the tenant from validated data.
     */
    private function createTenant(array $validated, Request $request): mixed
    {
        // Sanitize: strip HTML from descriptions (allow emojis)
        $descriptionEn = strip_tags(trim($validated['description_en'] ?? ''));
        $descriptionFr = strip_tags(trim($validated['description_fr'] ?? ''));

        $tenant = Tenant::create([
            'slug' => strtolower(trim($validated['subdomain'])),
            'name_en' => trim($validated['name_en']),
            'name_fr' => trim($validated['name_fr']),
            'custom_domain' => ! empty($validated['custom_domain'])
                ? strtolower(trim($validated['custom_domain']))
                : null,
            'description_en' => $descriptionEn !== '' ? $descriptionEn : null,
            'description_fr' => $descriptionFr !== '' ? $descriptionFr : null,
            'is_active' => $validated['is_active'] ?? true,
            'settings' => [],
        ]);

        // BR-062: Activity logging with admin context
        activity('tenants')
            ->performedOn($tenant)
            ->causedBy($request->user())
            ->withProperties([
                'slug' => $tenant->slug,
                'name_en' => $tenant->name_en,
                'name_fr' => $tenant->name_fr,
                'custom_domain' => $tenant->custom_domain,
                'is_active' => $tenant->is_active,
                'ip' => $request->ip(),
            ])
            ->log('created');

        // Redirect to tenant list with success toast
        session()->flash('toast', [
            'type' => 'success',
            'message' => __('Tenant ":name" created successfully.', ['name' => $tenant->name_en]),
        ]);

        if ($request->isGale()) {
            return gale()->redirect(url('/vault-entry/tenants'))->back('/vault-entry/tenants');
        }

        return redirect('/vault-entry/tenants');
    }

    /**
     * Update the tenant from validated data.
     *
     * BR-080: All edits recorded in activity log with admin as causer
     * Edge case: If no actual changes made, skip activity log but still show success toast
     */
    private function updateTenant(array $validated, Tenant $tenant, Request $request): mixed
    {
        $descriptionEn = strip_tags(trim($validated['description_en'] ?? ''));
        $descriptionFr = strip_tags(trim($validated['description_fr'] ?? ''));

        $newData = [
            'slug' => strtolower(trim($validated['subdomain'])),
            'name_en' => trim($validated['name_en']),
            'name_fr' => trim($validated['name_fr']),
            'custom_domain' => ! empty($validated['custom_domain'])
                ? strtolower(trim($validated['custom_domain']))
                : null,
            'description_en' => $descriptionEn !== '' ? $descriptionEn : null,
            'description_fr' => $descriptionFr !== '' ? $descriptionFr : null,
        ];

        // Detect actual changes for activity logging
        $changes = [];
        $oldValues = [];
        foreach ($newData as $key => $newValue) {
            $oldValue = $tenant->getAttribute($key);
            if ($oldValue !== $newValue) {
                $changes[$key] = $newValue;
                $oldValues[$key] = $oldValue;
            }
        }

        $tenant->update($newData);

        // BR-080: Only log if actual changes were made
        if (! empty($changes)) {
            activity('tenants')
                ->performedOn($tenant)
                ->causedBy($request->user())
                ->withProperties([
                    'old' => $oldValues,
                    'attributes' => $changes,
                    'ip' => $request->ip(),
                ])
                ->log('updated');
        }

        session()->flash('toast', [
            'type' => 'success',
            'message' => __('Tenant ":name" updated successfully.', ['name' => $tenant->name]),
        ]);

        if ($request->isGale()) {
            return gale()->redirect(url('/vault-entry/tenants/'.$tenant->slug))->back('/vault-entry/tenants/'.$tenant->slug);
        }

        return redirect('/vault-entry/tenants/'.$tenant->slug);
    }
}
