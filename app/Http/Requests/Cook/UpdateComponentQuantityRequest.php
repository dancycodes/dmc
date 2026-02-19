<?php

namespace App\Http\Requests\Cook;

use Illuminate\Foundation\Http\FormRequest;

class UpdateComponentQuantityRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * BR-346: Only users with manage-meals permission can configure quantities.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('can-manage-meals') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * F-124: Meal Component Quantity Settings
     * BR-339: Minimum quantity must be >= 0
     * BR-340: Maximum quantity must be >= minimum quantity (when both are set)
     * BR-341: Available quantity must be >= 0
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'min_quantity' => ['nullable', 'integer', 'min:0'],
            'max_quantity' => ['nullable', 'integer', 'min:1'],
            'available_quantity' => ['nullable', 'integer', 'min:0'],
        ];
    }

    /**
     * Get custom error messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'min_quantity.integer' => __('Minimum quantity must be a whole number.'),
            'min_quantity.min' => __('Minimum quantity cannot be negative.'),
            'max_quantity.integer' => __('Maximum quantity must be a whole number.'),
            'max_quantity.min' => __('Maximum quantity must be at least 1.'),
            'available_quantity.integer' => __('Available quantity must be a whole number.'),
            'available_quantity.min' => __('Available quantity cannot be negative.'),
        ];
    }
}
