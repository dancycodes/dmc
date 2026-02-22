<?php

namespace App\Http\Requests\Profile;

use App\Models\NotificationPreference;
use Illuminate\Foundation\Http\FormRequest;

class UpdateNotificationPreferencesRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * Validates a preferences array keyed by notification type,
     * with push_enabled and email_enabled booleans for each.
     */
    public function rules(): array
    {
        $types = implode(',', NotificationPreference::TYPES);
        $rules = [];

        foreach (NotificationPreference::TYPES as $type) {
            $rules["preferences.{$type}.push_enabled"] = ['sometimes', 'boolean'];
            $rules["preferences.{$type}.email_enabled"] = ['sometimes', 'boolean'];
        }

        return $rules;
    }

    /**
     * Custom messages for validation errors.
     */
    public function messages(): array
    {
        return [
            'preferences.*.push_enabled.boolean' => __('Invalid push notification preference value.'),
            'preferences.*.email_enabled.boolean' => __('Invalid email notification preference value.'),
        ];
    }
}
