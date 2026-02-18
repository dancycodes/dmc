<?php

namespace App\Http\Requests\Cook;

use App\Rules\ValidTimeFormat;
use Illuminate\Foundation\Http\FormRequest;

/**
 * F-100: Delivery/Pickup Time Interval Configuration
 * F-107: Schedule Validation Rules (enhanced with custom rules)
 *
 * Validates the delivery and pickup time interval configuration
 * for a cook schedule entry.
 *
 * BR-116: Both intervals on the open day (no day offset)
 * BR-119: Delivery end > delivery start
 * BR-120: Pickup end > pickup start
 * BR-121: At least one of delivery or pickup must be enabled
 * BR-122: Time format is 24-hour (HH:MM)
 * BR-172: Valid time format enforced via ValidTimeFormat rule
 * BR-178: Delivery and pickup intervals must be on the open day (day offset = 0)
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
            'delivery_start_time' => ['nullable', new ValidTimeFormat, 'required_if:delivery_enabled,true,1'],
            'delivery_end_time' => ['nullable', new ValidTimeFormat, 'required_if:delivery_enabled,true,1'],
            'pickup_enabled' => ['required'],
            'pickup_start_time' => ['nullable', new ValidTimeFormat, 'required_if:pickup_enabled,true,1'],
            'pickup_end_time' => ['nullable', new ValidTimeFormat, 'required_if:pickup_enabled,true,1'],
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
            'delivery_end_time.required_if' => __('Delivery end time is required when delivery is enabled.'),
            'pickup_enabled.required' => __('Pickup status is required.'),
            'pickup_start_time.required_if' => __('Pickup start time is required when pickup is enabled.'),
            'pickup_end_time.required_if' => __('Pickup end time is required when pickup is enabled.'),
        ];
    }
}
