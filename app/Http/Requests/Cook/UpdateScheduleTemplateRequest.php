<?php

namespace App\Http\Requests\Cook;

use App\Models\CookSchedule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * F-103: Edit Schedule Template
 *
 * Validates the schedule template edit form.
 * Same rules as StoreScheduleTemplateRequest (BR-140).
 *
 * BR-140: All validation rules from F-099 and F-100 apply
 * BR-141: Template name must remain unique within the tenant
 * BR-145: Only users with manage-schedules permission can edit
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
            'order_start_time' => ['required', 'date_format:H:i'],
            'order_start_day_offset' => ['required', 'integer', 'min:0', 'max:'.CookSchedule::MAX_START_DAY_OFFSET],
            'order_end_time' => ['required', 'date_format:H:i'],
            'order_end_day_offset' => ['required', 'integer', 'min:0', 'max:'.CookSchedule::MAX_END_DAY_OFFSET],
            'delivery_enabled' => ['required'],
            'delivery_start_time' => ['nullable', 'date_format:H:i'],
            'delivery_end_time' => ['nullable', 'date_format:H:i'],
            'pickup_enabled' => ['required'],
            'pickup_start_time' => ['nullable', 'date_format:H:i'],
            'pickup_end_time' => ['nullable', 'date_format:H:i'],
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
            'order_start_time.date_format' => __('Order start time must be in HH:MM format (24-hour).'),
            'order_start_day_offset.required' => __('Start day offset is required.'),
            'order_start_day_offset.max' => __('Start day offset cannot exceed :max days before.', ['max' => CookSchedule::MAX_START_DAY_OFFSET]),
            'order_end_time.required' => __('Order end time is required.'),
            'order_end_time.date_format' => __('Order end time must be in HH:MM format (24-hour).'),
            'order_end_day_offset.required' => __('End day offset is required.'),
            'order_end_day_offset.max' => __('End day offset cannot exceed :max day before.', ['max' => CookSchedule::MAX_END_DAY_OFFSET]),
            'delivery_enabled.required' => __('Delivery status is required.'),
            'delivery_start_time.date_format' => __('Delivery start time must be in HH:MM format (24-hour).'),
            'delivery_end_time.date_format' => __('Delivery end time must be in HH:MM format (24-hour).'),
            'pickup_enabled.required' => __('Pickup status is required.'),
            'pickup_start_time.date_format' => __('Pickup start time must be in HH:MM format (24-hour).'),
            'pickup_end_time.date_format' => __('Pickup end time must be in HH:MM format (24-hour).'),
        ];
    }
}
