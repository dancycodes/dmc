<?php

namespace App\Http\Requests\Admin;

use App\Http\Controllers\Admin\RoleController;
use Illuminate\Foundation\Http\FormRequest;
use Spatie\Permission\Models\Role;

class UpdateRoleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * BR-105: Only users with can-manage-roles permission can edit roles.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('can-manage-roles') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * BR-116: System role names cannot be changed
     * BR-117: System role descriptions can be updated
     * BR-118: Custom role names can be changed but must remain unique
     * BR-119: Role name uniqueness check excludes the current role's own name
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $role = $this->route('role');

        $rules = [
            'description' => ['nullable', 'string', 'max:500'],
        ];

        // BR-116: System roles cannot have their names changed, so name fields are not validated
        // BR-118: Custom roles can have their names changed
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
                    if (in_array($machineName, RoleController::SYSTEM_ROLE_NAMES, true)) {
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
     * Get custom error messages for validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name_en.required' => __('The English name is required.'),
            'name_en.min' => __('The English name must be at least :min characters.'),
            'name_en.max' => __('The English name must not exceed :max characters.'),
            'name_en.regex' => __('The English name may only contain letters, numbers, hyphens, and spaces.'),
            'name_fr.required' => __('The French name is required.'),
            'name_fr.min' => __('The French name must be at least :min characters.'),
            'name_fr.max' => __('The French name must not exceed :max characters.'),
            'name_fr.regex' => __('The French name may only contain letters, numbers, hyphens, spaces, and accented characters.'),
            'description.max' => __('The description must not exceed :max characters.'),
        ];
    }
}
