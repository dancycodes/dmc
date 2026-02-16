<?php

namespace App\Http\Requests\Profile;

use Illuminate\Foundation\Http\FormRequest;

class UpdateLanguagePreferenceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * Authorization is enforced by auth middleware on the route.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * BR-184: Supported languages: "en" (English) and "fr" (French).
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'preferred_language' => ['required', 'string', 'in:en,fr'],
        ];
    }

    /**
     * Get custom error messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'preferred_language.required' => __('Please select a preferred language.'),
            'preferred_language.in' => __('The selected language is not supported.'),
        ];
    }
}
