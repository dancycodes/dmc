<?php

namespace App\Http\Requests\Cook;

use Illuminate\Foundation\Http\FormRequest;

class StoreTownRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * BR-212: Only users with location management permission can add towns.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('can-manage-locations') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * BR-207: Town name required in both EN and FR.
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
     * BR-211: All validation messages use __() localization.
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
