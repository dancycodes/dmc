<?php

namespace App\Http\Requests\Admin;

use App\Models\Announcement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * F-195: Update Announcement Form Request
 *
 * Used when editing a draft or scheduled announcement.
 * Same rules as StoreAnnouncementRequest.
 */
class UpdateAnnouncementRequest extends FormRequest
{
    public const MIN_SCHEDULE_MINUTES = 5;

    public function authorize(): bool
    {
        return $this->user()?->can('can-access-admin-panel') ?? false;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'content' => ['required', 'string', 'max:2000'],
            'target_type' => ['required', Rule::in(array_keys(Announcement::targetTypeOptions()))],
            'target_tenant_id' => [
                Rule::requiredIf(fn () => $this->input('target_type') === Announcement::TARGET_SPECIFIC_TENANT),
                'nullable',
                'exists:tenants,id',
            ],
            'scheduled_at' => [
                'nullable',
                'date',
                'after:'.now()->addMinutes(self::MIN_SCHEDULE_MINUTES)->format('Y-m-d H:i:s'),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'content.required' => __('Announcement content is required.'),
            'content.max' => __('Announcement content cannot exceed 2000 characters.'),
            'target_type.required' => __('Please select a target audience.'),
            'target_type.in' => __('Invalid target audience selected.'),
            'target_tenant_id.required' => __('Please select a tenant when targeting a specific tenant.'),
            'target_tenant_id.exists' => __('The selected tenant does not exist.'),
            'scheduled_at.after' => __('Scheduled time must be at least :minutes minutes in the future.', [
                'minutes' => self::MIN_SCHEDULE_MINUTES,
            ]),
        ];
    }
}
