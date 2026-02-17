<?php

namespace App\Http\Requests\Cook;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTownRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * BR-223: Edit action requires location management permission.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('can-manage-locations') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * BR-219: Town name required in both EN and FR.
     * Max 255 characters per name.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'name_en' => ['required', 'string', 'max:255'],
            'name_fr' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * Get custom validation messages.
     *
     * BR-224: All validation messages use __() localization.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name_en.required' => __('Town name is required in English.'),
            'name_en.max' => __('Town name must not exceed 255 characters.'),
            'name_fr.required' => __('Town name is required in French.'),
            'name_fr.max' => __('Town name must not exceed 255 characters.'),
        ];
    }
}
