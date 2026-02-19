<?php

namespace App\Http\Requests\Cook;

use App\Models\SellingUnit;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSellingUnitRequest extends FormRequest
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
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'name_en' => ['required', 'string', 'max:'.SellingUnit::NAME_MAX_LENGTH],
            'name_fr' => ['required', 'string', 'max:'.SellingUnit::NAME_MAX_LENGTH],
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
            'name_en.required' => __('Unit name is required in English.'),
            'name_en.max' => __('Unit name must not exceed :max characters.'),
            'name_fr.required' => __('Unit name is required in French.'),
            'name_fr.max' => __('Unit name must not exceed :max characters.'),
        ];
    }
}
