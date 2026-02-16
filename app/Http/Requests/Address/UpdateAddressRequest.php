<?php

namespace App\Http\Requests\Address;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class UpdateAddressRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Auth::check();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * BR-135: Same validation rules as StoreAddressRequest.
     * BR-137: Label uniqueness excludes the current address being edited.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $userId = Auth::id();
        $addressId = $this->route('address')?->id;

        return [
            'label' => [
                'required',
                'string',
                'max:50',
                Rule::unique('addresses', 'label')
                    ->where('user_id', $userId)
                    ->ignore($addressId),
            ],
            'town_id' => [
                'required',
                'integer',
                'exists:towns,id',
            ],
            'quarter_id' => [
                'required',
                'integer',
                Rule::exists('quarters', 'id')->where('town_id', $this->input('town_id')),
            ],
            'neighbourhood' => [
                'nullable',
                'string',
                'max:255',
            ],
            'additional_directions' => [
                'nullable',
                'string',
                'max:500',
            ],
            'latitude' => [
                'nullable',
                'numeric',
                'between:-90,90',
            ],
            'longitude' => [
                'nullable',
                'numeric',
                'between:-180,180',
            ],
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
            'label.required' => __('Address label is required.'),
            'label.max' => __('Address label must not exceed 50 characters.'),
            'label.unique' => __('You already have an address with this label.'),
            'town_id.required' => __('Town is required.'),
            'town_id.exists' => __('The selected town is not available.'),
            'quarter_id.required' => __('Quarter is required.'),
            'quarter_id.exists' => __('The selected quarter does not belong to the chosen town.'),
            'neighbourhood.max' => __('Neighbourhood must not exceed 255 characters.'),
            'additional_directions.max' => __('Directions must not exceed 500 characters.'),
        ];
    }
}
