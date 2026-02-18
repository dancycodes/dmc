<?php

namespace App\Http\Requests\Cook;

use App\Models\CookSchedule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * F-099: Order Time Interval Configuration
 *
 * Validates the order time interval configuration for a cook schedule entry.
 *
 * BR-106: Start = time + day offset (0-7)
 * BR-107: End = time + day offset (0-1)
 * BR-109: Time format is 24-hour (HH:MM)
 * BR-110: Start day offset max 7
 * BR-111: End day offset max 1
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
            'order_start_time' => ['required', 'date_format:H:i'],
            'order_start_day_offset' => ['required', 'integer', 'min:0', 'max:'.CookSchedule::MAX_START_DAY_OFFSET],
            'order_end_time' => ['required', 'date_format:H:i'],
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
            'order_start_time.date_format' => __('Start time must be in HH:MM format (24-hour).'),
            'order_start_day_offset.required' => __('Start day offset is required.'),
            'order_start_day_offset.integer' => __('Start day offset must be a number.'),
            'order_start_day_offset.min' => __('Start day offset cannot be negative.'),
            'order_start_day_offset.max' => __('Start day offset cannot exceed :max days before.', ['max' => CookSchedule::MAX_START_DAY_OFFSET]),
            'order_end_time.required' => __('End time is required.'),
            'order_end_time.date_format' => __('End time must be in HH:MM format (24-hour).'),
            'order_end_day_offset.required' => __('End day offset is required.'),
            'order_end_day_offset.integer' => __('End day offset must be a number.'),
            'order_end_day_offset.min' => __('End day offset cannot be negative.'),
            'order_end_day_offset.max' => __('End day offset cannot exceed :max day before.', ['max' => CookSchedule::MAX_END_DAY_OFFSET]),
        ];
    }
}
