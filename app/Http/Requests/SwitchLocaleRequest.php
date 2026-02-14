<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SwitchLocaleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * Everyone (guests and authenticated users) can switch language.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $availableLocales = implode(',', config('app.available_locales', ['en', 'fr']));

        return [
            'locale' => ['required', 'string', "in:{$availableLocales}"],
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
            'locale.required' => __('Please select a language.'),
            'locale.in' => __('The selected language is not supported.'),
        ];
    }
}
