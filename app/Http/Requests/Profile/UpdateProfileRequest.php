<?php

namespace App\Http\Requests\Profile;

use App\Http\Requests\Auth\RegisterRequest;
use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileRequest extends FormRequest
{
    /**
     * Cameroon phone regex: +237 followed by 9 digits starting with 6, 7, or 2.
     *
     * Reuses the same regex constant from RegisterRequest for consistency.
     */
    public const CAMEROON_PHONE_REGEX = RegisterRequest::CAMEROON_PHONE_REGEX;

    /**
     * Determine if the user is authorized to make this request.
     *
     * BR-114: Users can only edit their own profile (enforced by auth middleware).
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * BR-112: Name is required, 2-255 characters.
     * BR-113: Phone must match Cameroon format (+237 followed by 9 digits).
     * BR-115: Preferred language must be "en" or "fr".
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'phone' => ['required', 'string', 'regex:'.self::CAMEROON_PHONE_REGEX],
            'preferred_language' => ['required', 'string', 'in:en,fr'],
        ];
    }

    /**
     * Get custom error messages for validator errors.
     *
     * BR-118: All validation error messages are localized via __().
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => __('Name is required.'),
            'name.min' => __('Name must be at least 2 characters.'),
            'name.max' => __('Name must not exceed 255 characters.'),
            'phone.required' => __('Phone number is required.'),
            'phone.regex' => __('Please enter a valid Cameroon phone number (+237 followed by 9 digits).'),
            'preferred_language.required' => __('Please select a preferred language.'),
            'preferred_language.in' => __('The selected language is not supported.'),
        ];
    }

    /**
     * Prepare the data for validation.
     *
     * Normalizes name (trim) and phone (strip spaces/dashes, ensure +237 prefix).
     * Edge case: phone with spaces or dashes is normalized before validation.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('name')) {
            $this->merge([
                'name' => trim($this->input('name')),
            ]);
        }

        if ($this->has('phone') && $this->input('phone') !== null && $this->input('phone') !== '') {
            $this->merge([
                'phone' => RegisterRequest::normalizePhone($this->input('phone')),
            ]);
        }
    }
}
