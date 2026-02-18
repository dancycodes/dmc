<?php

namespace App\Http\Requests\Cook;

use Illuminate\Foundation\Http\FormRequest;

class StorePickupLocationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * BR-288: Only users with location management permission can add pickup locations.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('can-manage-locations') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * BR-281: Location name required in both EN and FR.
     * BR-282: Town selection required.
     * BR-283: Quarter selection required.
     * BR-284: Address required, max 500 characters.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'name_en' => ['required', 'string', 'max:255'],
            'name_fr' => ['required', 'string', 'max:255'],
            'town_id' => ['required', 'integer', 'exists:towns,id'],
            'quarter_id' => ['required', 'integer', 'exists:quarters,id'],
            'address' => ['required', 'string', 'max:500'],
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
            'name_en.required' => __('Location name is required in English.'),
            'name_en.max' => __('Location name must not exceed 255 characters.'),
            'name_fr.required' => __('Location name is required in French.'),
            'name_fr.max' => __('Location name must not exceed 255 characters.'),
            'town_id.required' => __('Please select a town.'),
            'town_id.exists' => __('The selected town is invalid.'),
            'quarter_id.required' => __('Please select a quarter.'),
            'quarter_id.exists' => __('The selected quarter is invalid.'),
            'address.required' => __('Address is required.'),
            'address.max' => __('Address must not exceed 500 characters.'),
        ];
    }
}
