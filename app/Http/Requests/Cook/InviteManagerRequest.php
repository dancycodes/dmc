<?php

namespace App\Http\Requests\Cook;

use Illuminate\Foundation\Http\FormRequest;

/**
 * F-209: Cook Creates Manager Role — HTTP fallback validation for invite.
 *
 * Used only for non-Gale (traditional HTTP) requests.
 * Gale requests use validateState() directly in the controller.
 */
class InviteManagerRequest extends FormRequest
{
    public function authorize(): bool
    {
        $tenant = tenant();
        $user = $this->user();

        return $tenant && ($tenant->cook_id === $user?->id || $user?->hasRole('super-admin'));
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.required' => __('Please enter an email address.'),
            'email.email' => __('Please enter a valid email address.'),
        ];
    }
}
