<?php

namespace App\Http\Requests\Cook;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMealRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * BR-215: Only users with can-manage-meals permission can edit meals.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('can-manage-meals') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * BR-210: Meal name required in both EN and FR.
     * BR-211: Meal description required in both EN and FR.
     * BR-213: Name max length: 150 characters per language.
     * BR-214: Description max length: 2000 characters per language.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'name_en' => ['required', 'string', 'max:150'],
            'name_fr' => ['required', 'string', 'max:150'],
            'description_en' => ['required', 'string', 'max:2000'],
            'description_fr' => ['required', 'string', 'max:2000'],
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
            'name_en.required' => __('Meal name is required in English.'),
            'name_en.max' => __('Meal name must not exceed :max characters.'),
            'name_fr.required' => __('Meal name is required in French.'),
            'name_fr.max' => __('Meal name must not exceed :max characters.'),
            'description_en.required' => __('Meal description is required in English.'),
            'description_en.max' => __('Meal description must not exceed :max characters.'),
            'description_fr.required' => __('Meal description is required in French.'),
            'description_fr.max' => __('Meal description must not exceed :max characters.'),
        ];
    }
}
