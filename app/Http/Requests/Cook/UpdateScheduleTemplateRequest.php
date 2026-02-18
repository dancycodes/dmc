<?php

namespace App\Http\Requests\Cook;

use App\Models\CookSchedule;
use App\Rules\ValidTimeFormat;
use Illuminate\Foundation\Http\FormRequest;

/**
 * F-103: Edit Schedule Template
 * F-107: Schedule Validation Rules (enhanced with ValidTimeFormat rule)
 *
 * Validates the schedule template edit form.
 * Same rules as StoreScheduleTemplateRequest (BR-140).
 *
 * BR-140: All validation rules from F-099 and F-100 apply
 * BR-141: Template name must remain unique within the tenant
 * BR-145: Only users with manage-schedules permission can edit
 * BR-172: Valid time format via ValidTimeFormat rule
 * BR-184: All validation rules apply equally to templates
 */
class UpdateScheduleTemplateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('can-manage-schedules') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'order_start_time' => ['required', new ValidTimeFormat],
            'order_start_day_offset' => ['required', 'integer', 'min:0', 'max:'.CookSchedule::MAX_START_DAY_OFFSET],
            'order_end_time' => ['required', new ValidTimeFormat],
            'order_end_day_offset' => ['required', 'integer', 'min:0', 'max:'.CookSchedule::MAX_END_DAY_OFFSET],
            'delivery_enabled' => ['required'],
            'delivery_start_time' => ['nullable', new ValidTimeFormat],
            'delivery_end_time' => ['nullable', new ValidTimeFormat],
            'pickup_enabled' => ['required'],
            'pickup_start_time' => ['nullable', new ValidTimeFormat],
            'pickup_end_time' => ['nullable', new ValidTimeFormat],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => __('Template name is required.'),
            'name.max' => __('Template name must not exceed 100 characters.'),
            'order_start_time.required' => __('Order start time is required.'),
            'order_start_day_offset.required' => __('Start day offset is required.'),
            'order_start_day_offset.max' => __('Order window cannot start more than :max days before the open day.', ['max' => CookSchedule::MAX_START_DAY_OFFSET]),
            'order_end_time.required' => __('Order end time is required.'),
            'order_end_day_offset.required' => __('End day offset is required.'),
            'order_end_day_offset.max' => __('Order end day offset cannot exceed :max.', ['max' => CookSchedule::MAX_END_DAY_OFFSET]),
            'delivery_enabled.required' => __('Delivery status is required.'),
            'pickup_enabled.required' => __('Pickup status is required.'),
        ];
    }
}
