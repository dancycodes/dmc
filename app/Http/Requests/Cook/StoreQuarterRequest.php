<?php

namespace App\Http\Requests\Cook;

use Illuminate\Foundation\Http\FormRequest;

class StoreQuarterRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * BR-241: Only users with location management permission can add quarters.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('can-manage-locations') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * BR-232: Quarter name required in both EN and FR.
     * BR-234: Delivery fee is required and must be >= 0.
     * BR-235: Delivery fee stored as integer.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'name_en' => ['required', 'string', 'max:255'],
            'name_fr' => ['required', 'string', 'max:255'],
            'delivery_fee' => ['required', 'integer', 'min:0'],
        ];
    }

    /**
     * Get custom validation messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name_en.required' => __('Quarter name is required in English.'),
            'name_en.max' => __('Quarter name must not exceed 255 characters.'),
            'name_fr.required' => __('Quarter name is required in French.'),
            'name_fr.max' => __('Quarter name must not exceed 255 characters.'),
            'delivery_fee.required' => __('Delivery fee is required.'),
            'delivery_fee.integer' => __('Delivery fee must be a whole number.'),
            'delivery_fee.min' => __('Delivery fee must be 0 or greater.'),
        ];
    }
}
