<?php

namespace App\Http\Requests\Cook;

use App\Models\CookSchedule;
use App\Rules\ValidTimeFormat;
use Illuminate\Foundation\Http\FormRequest;

/**
 * F-099: Order Time Interval Configuration
 * F-107: Schedule Validation Rules (enhanced with custom rules)
 *
 * Validates the order time interval configuration for a cook schedule entry.
 *
 * BR-106: Start = time + day offset (0-7)
 * BR-107: End = time + day offset (0-1)
 * BR-109: Time format is 24-hour (HH:MM)
 * BR-110: Start day offset max 7
 * BR-111: End day offset max 1
 * BR-172: Valid time format enforced via ValidTimeFormat rule
 * BR-180: Start day offset cannot exceed 7
 * BR-181: End day offset can be 0 or 1 only
 */
class UpdateOrderIntervalRequest extends FormRequest
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
            'order_start_time' => ['required', new ValidTimeFormat],
            'order_start_day_offset' => ['required', 'integer', 'min:0', 'max:'.CookSchedule::MAX_START_DAY_OFFSET],
            'order_end_time' => ['required', new ValidTimeFormat],
            'order_end_day_offset' => ['required', 'integer', 'min:0', 'max:'.CookSchedule::MAX_END_DAY_OFFSET],
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
            'order_start_time.required' => __('Start time is required.'),
            'order_start_day_offset.required' => __('Start day offset is required.'),
            'order_start_day_offset.integer' => __('Start day offset must be a number.'),
            'order_start_day_offset.min' => __('Start day offset cannot be negative.'),
            'order_start_day_offset.max' => __('Order window cannot start more than :max days before the open day.', ['max' => CookSchedule::MAX_START_DAY_OFFSET]),
            'order_end_time.required' => __('End time is required.'),
            'order_end_day_offset.required' => __('End day offset is required.'),
            'order_end_day_offset.integer' => __('End day offset must be a number.'),
            'order_end_day_offset.min' => __('End day offset cannot be negative.'),
            'order_end_day_offset.max' => __('Order end day offset cannot exceed :max.', ['max' => CookSchedule::MAX_END_DAY_OFFSET]),
        ];
    }
}
