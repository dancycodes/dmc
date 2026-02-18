<?php

namespace App\Http\Requests\Cook;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMealLocationOverrideRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('can-manage-meals') && $this->user()?->can('can-manage-delivery-areas');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'has_custom_locations' => ['required', 'boolean'],
            'quarters' => ['array'],
            'quarters.*.quarter_id' => ['required_with:quarters', 'integer', 'exists:quarters,id'],
            'quarters.*.custom_fee' => ['nullable', 'integer', 'min:0'],
            'pickup_location_ids' => ['array'],
            'pickup_location_ids.*' => ['integer', 'exists:pickup_locations,id'],
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
            'quarters.*.quarter_id.required_with' => __('Each quarter must have a valid ID.'),
            'quarters.*.quarter_id.exists' => __('The selected quarter is invalid.'),
            'quarters.*.custom_fee.min' => __('Delivery fee must be at least :min XAF.'),
            'pickup_location_ids.*.exists' => __('The selected pickup location is invalid.'),
        ];
    }
}
