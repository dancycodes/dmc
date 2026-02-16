<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreTenantRequest;
use App\Models\Tenant;
use App\Services\TenantService;
use Illuminate\Http\Request;

class TenantController extends Controller
{
    /**
     * List all tenants (stub for F-046, provides redirect target for F-045).
     */
    public function index(Request $request): mixed
    {
        $tenants = Tenant::query()
            ->orderBy('created_at', 'desc')
            ->get();

        return gale()->view('admin.tenants.index', [
            'tenants' => $tenants,
            'mainDomain' => TenantService::mainDomain(),
        ], web: true);
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
            $validated = $request->validateState($this->validationRules());

            return $this->createTenant($validated, $request);
        }

        // Traditional HTTP: use StoreTenantRequest validation via manual resolution
        $formRequest = app(StoreTenantRequest::class);

        return $this->createTenant($formRequest->validated(), $request);
    }

    /**
     * Get the shared validation rules for Gale and HTTP paths.
     *
     * @return array<string, array<int, mixed>>
     */
    private function validationRules(): array
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
}
