<?php

namespace App\Http\Requests\Cook;

use Illuminate\Foundation\Http\FormRequest;

class StoreMealComponentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('can-manage-meals') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * F-118: Meal Component Creation
     * BR-278: Name required in both EN and FR
     * BR-279: Name max length 150 characters per language
     * BR-280: Price required, minimum 1 XAF
     * BR-281: Selling unit required
     * BR-282: Standard units list
     * BR-283: Min quantity defaults to 0
     * BR-284: Max quantity defaults to unlimited (null)
     * BR-285: Available quantity defaults to unlimited (null)
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name_en' => ['required', 'string', 'max:150'],
            'name_fr' => ['required', 'string', 'max:150'],
            'price' => ['required', 'integer', 'min:1'],
            'selling_unit' => ['required', 'string', 'max:50'],
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
            'name_en.required' => __('Component name is required in English.'),
            'name_en.max' => __('Component name must not exceed :max characters.'),
            'name_fr.required' => __('Component name is required in French.'),
            'name_fr.max' => __('Component name must not exceed :max characters.'),
            'price.required' => __('Price is required.'),
            'price.integer' => __('Price must be a whole number.'),
            'price.min' => __('Price must be at least 1 XAF.'),
            'selling_unit.required' => __('Selling unit is required.'),
            'min_quantity.integer' => __('Minimum quantity must be a whole number.'),
            'min_quantity.min' => __('Minimum quantity cannot be negative.'),
            'max_quantity.integer' => __('Maximum quantity must be a whole number.'),
            'max_quantity.min' => __('Maximum quantity must be at least 1.'),
            'available_quantity.integer' => __('Available quantity must be a whole number.'),
            'available_quantity.min' => __('Available quantity cannot be negative.'),
        ];
    }
}
