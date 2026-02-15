<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    /**
     * Cameroon phone regex: +237 followed by 9 digits starting with 6, 7, or 2.
     *
     * Accepts with or without +237 prefix (normalized in prepareForValidation).
     */
    public const CAMEROON_PHONE_REGEX = '/^\+237[672]\d{8}$/';

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:1', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['required', 'string', 'regex:'.self::CAMEROON_PHONE_REGEX],
            'password' => [
                'required',
                'string',
                Password::min(8)
                    ->mixedCase()
                    ->numbers(),
                'confirmed',
            ],
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
            'name.required' => __('Name is required.'),
            'name.min' => __('Name must be at least one character.'),
            'name.max' => __('Name must not exceed 255 characters.'),
            'email.required' => __('Email address is required.'),
            'email.email' => __('Please enter a valid email address.'),
            'email.unique' => __('This email is already registered.'),
            'phone.required' => __('Phone number is required.'),
            'phone.regex' => __('Please enter a valid Cameroon phone number.'),
            'password.required' => __('Password is required.'),
            'password.confirmed' => __('Password confirmation does not match.'),
        ];
    }

    /**
     * Prepare the data for validation.
     *
     * Normalizes name (trim), email (lowercase trim), and phone
     * (strip spaces/dashes, ensure +237 prefix) before validation runs.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('name')) {
            $this->merge([
                'name' => trim($this->input('name')),
            ]);
        }

        if ($this->has('email')) {
            $this->merge([
                'email' => strtolower(trim($this->input('email'))),
            ]);
        }

        if ($this->has('phone')) {
            $this->merge([
                'phone' => self::normalizePhone($this->input('phone')),
            ]);
        }
    }

    /**
     * Normalize a phone number to +237XXXXXXXXX format.
     *
     * Strips spaces, dashes, parentheses. Prepends +237 if missing.
     */
    public static function normalizePhone(string $phone): string
    {
        $phone = preg_replace('/[\s\-()]/', '', $phone);

        if (str_starts_with($phone, '+237')) {
            return $phone;
        }

        if (str_starts_with($phone, '237') && strlen($phone) === 12) {
            return '+'.$phone;
        }

        if (strlen($phone) === 9 && preg_match('/^[672]/', $phone)) {
            return '+237'.$phone;
        }

        return $phone;
    }
}
