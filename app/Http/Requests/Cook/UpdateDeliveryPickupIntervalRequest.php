<?php

namespace App\Http\Requests\Cook;

use Illuminate\Foundation\Http\FormRequest;

/**
 * F-100: Delivery/Pickup Time Interval Configuration
 *
 * Validates the delivery and pickup time interval configuration
 * for a cook schedule entry.
 *
 * BR-116: Both intervals on the open day (no day offset)
 * BR-119: Delivery end > delivery start
 * BR-120: Pickup end > pickup start
 * BR-121: At least one of delivery or pickup must be enabled
 * BR-122: Time format is 24-hour (HH:MM)
 */
class UpdateDeliveryPickupIntervalRequest extends FormRequest
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
            'delivery_enabled' => ['required'],
            'delivery_start_time' => ['nullable', 'date_format:H:i', 'required_if:delivery_enabled,true,1'],
            'delivery_end_time' => ['nullable', 'date_format:H:i', 'required_if:delivery_enabled,true,1'],
            'pickup_enabled' => ['required'],
            'pickup_start_time' => ['nullable', 'date_format:H:i', 'required_if:pickup_enabled,true,1'],
            'pickup_end_time' => ['nullable', 'date_format:H:i', 'required_if:pickup_enabled,true,1'],
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
            'delivery_enabled.required' => __('Delivery status is required.'),
            'delivery_start_time.required_if' => __('Delivery start time is required when delivery is enabled.'),
            'delivery_start_time.date_format' => __('Delivery start time must be in HH:MM format (24-hour).'),
            'delivery_end_time.required_if' => __('Delivery end time is required when delivery is enabled.'),
            'delivery_end_time.date_format' => __('Delivery end time must be in HH:MM format (24-hour).'),
            'pickup_enabled.required' => __('Pickup status is required.'),
            'pickup_start_time.required_if' => __('Pickup start time is required when pickup is enabled.'),
            'pickup_start_time.date_format' => __('Pickup start time must be in HH:MM format (24-hour).'),
            'pickup_end_time.required_if' => __('Pickup end time is required when pickup is enabled.'),
            'pickup_end_time.date_format' => __('Pickup end time must be in HH:MM format (24-hour).'),
        ];
    }
}
