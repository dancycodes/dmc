<?php

namespace App\Http\Requests\Admin;

use App\Models\Tenant;
use App\Services\TenantService;
use Illuminate\Foundation\Http\FormRequest;

class StoreTenantRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('can-create-tenant') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * BR-056: Subdomain must be unique across all tenants
     * BR-057: Subdomain format: lowercase letters, numbers, hyphens; 3-63 chars; start/end with letter/number
     * BR-058: Custom domain optional but must be valid hostname
     * BR-059: Custom domain must not conflict with platform or existing tenant domains
     * BR-060: Both name_en and name_fr required; both description_en and description_fr required
     * BR-061: Status defaults to active
     * BR-063: Reserved subdomains rejected
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
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

                    // BR-059: Cannot be the platform's main domain
                    if ($normalizedValue === strtolower($mainDomain)) {
                        $fail(__('This domain conflicts with the platform domain.'));
                    }

                    // Also reject subdomains of the main domain
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
     * Get custom error messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name_en.required' => __('English name is required.'),
            'name_en.max' => __('Name must not exceed 255 characters.'),
            'name_fr.required' => __('French name is required.'),
            'name_fr.max' => __('Name must not exceed 255 characters.'),
            'subdomain.required' => __('Subdomain is required.'),
            'subdomain.min' => __('Subdomain must be at least 3 characters.'),
            'subdomain.max' => __('Subdomain must not exceed 63 characters.'),
            'subdomain.regex' => __('Subdomain may only contain lowercase letters, numbers, and hyphens.'),
            'subdomain.not_regex' => __('Subdomain must not contain consecutive hyphens.'),
            'subdomain.unique' => __('This subdomain is already taken.'),
            'custom_domain.regex' => __('Please enter a valid domain name (e.g., example.cm).'),
            'custom_domain.unique' => __('This domain is already in use by another tenant.'),
            'description_en.required' => __('English description is required.'),
            'description_en.max' => __('Description must not exceed 5000 characters.'),
            'description_fr.required' => __('French description is required.'),
            'description_fr.max' => __('Description must not exceed 5000 characters.'),
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('subdomain')) {
            $this->merge([
                'subdomain' => strtolower(trim($this->input('subdomain'))),
            ]);
        }

        if ($this->has('custom_domain') && $this->input('custom_domain') !== null && $this->input('custom_domain') !== '') {
            $this->merge([
                'custom_domain' => strtolower(trim($this->input('custom_domain'))),
            ]);
        } else {
            $this->merge([
                'custom_domain' => null,
            ]);
        }

        if ($this->has('name_en')) {
            $this->merge(['name_en' => trim($this->input('name_en'))]);
        }

        if ($this->has('name_fr')) {
            $this->merge(['name_fr' => trim($this->input('name_fr'))]);
        }

        // Strip HTML from descriptions (sanitize), allow emojis
        if ($this->has('description_en')) {
            $this->merge(['description_en' => strip_tags(trim($this->input('description_en')))]);
        }

        if ($this->has('description_fr')) {
            $this->merge(['description_fr' => strip_tags(trim($this->input('description_fr')))]);
        }
    }
}
