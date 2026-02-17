<?php

namespace App\Http\Requests\Cook;

use App\Http\Requests\Auth\RegisterRequest;
use Illuminate\Foundation\Http\FormRequest;

class UpdateBrandInfoRequest extends FormRequest
{
    /**
     * Cameroon phone regex: +237 followed by 9 digits starting with 6, 7, or 2.
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
     * F-072: Brand Info Step validation rules.
     * BR-117: Brand name required in both EN and FR.
     * BR-118: If bio provided in one language, must be in both.
     * BR-119: WhatsApp number required, valid Cameroon format.
     * BR-120: Phone optional but valid Cameroon format if provided.
     * BR-121: Social links all optional.
     * BR-122: Social link URLs must be valid if provided.
     * BR-123: Brand name max 100 chars per language.
     * BR-124: Bio max 1000 chars per language.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name_en' => ['required', 'string', 'max:100'],
            'name_fr' => ['required', 'string', 'max:100'],
            'description_en' => ['nullable', 'string', 'max:1000'],
            'description_fr' => ['nullable', 'string', 'max:1000'],
            'whatsapp' => ['required', 'string', 'regex:'.self::CAMEROON_PHONE_REGEX],
            'phone' => ['nullable', 'string', 'regex:'.self::CAMEROON_PHONE_REGEX],
            'social_facebook' => ['nullable', 'string', 'url', 'max:500'],
            'social_instagram' => ['nullable', 'string', 'url', 'max:500'],
            'social_tiktok' => ['nullable', 'string', 'url', 'max:500'],
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
            'name_en.required' => __('Brand name is required in English.'),
            'name_en.max' => __('Brand name must not exceed 100 characters.'),
            'name_fr.required' => __('Brand name is required in French.'),
            'name_fr.max' => __('Brand name must not exceed 100 characters.'),
            'description_en.max' => __('Bio must not exceed 1000 characters.'),
            'description_fr.max' => __('Bio must not exceed 1000 characters.'),
            'whatsapp.required' => __('WhatsApp number is required.'),
            'whatsapp.regex' => __('Please enter a valid Cameroon phone number.'),
            'phone.regex' => __('Please enter a valid Cameroon phone number.'),
            'social_facebook.url' => __('Please enter a valid URL.'),
            'social_instagram.url' => __('Please enter a valid URL.'),
            'social_tiktok.url' => __('Please enter a valid URL.'),
        ];
    }

    /**
     * Configure the validator instance.
     *
     * BR-118: If bio is provided in one language, it must be provided in both.
     */
    public function withValidator(\Illuminate\Validation\Validator $validator): void
    {
        $validator->after(function (\Illuminate\Validation\Validator $validator) {
            $descEn = $this->input('description_en');
            $descFr = $this->input('description_fr');

            $hasEn = ! empty($descEn) && trim($descEn) !== '';
            $hasFr = ! empty($descFr) && trim($descFr) !== '';

            if ($hasEn && ! $hasFr) {
                $validator->errors()->add(
                    'description_fr',
                    __('Bio is required in French when provided in English.')
                );
            }

            if ($hasFr && ! $hasEn) {
                $validator->errors()->add(
                    'description_en',
                    __('Bio is required in English when provided in French.')
                );
            }
        });
    }

    /**
     * Prepare the data for validation.
     *
     * Normalizes phone numbers (strip spaces/dashes, ensure +237 prefix).
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('whatsapp') && $this->input('whatsapp') !== null && $this->input('whatsapp') !== '') {
            $this->merge([
                'whatsapp' => RegisterRequest::normalizePhone($this->input('whatsapp')),
            ]);
        }

        if ($this->has('phone') && $this->input('phone') !== null && $this->input('phone') !== '') {
            $this->merge([
                'phone' => RegisterRequest::normalizePhone($this->input('phone')),
            ]);
        }

        // Trim text fields
        foreach (['name_en', 'name_fr', 'description_en', 'description_fr'] as $field) {
            if ($this->has($field) && $this->input($field) !== null) {
                $this->merge([
                    $field => trim($this->input($field)),
                ]);
            }
        }

        // Clean empty social links to null
        foreach (['social_facebook', 'social_instagram', 'social_tiktok'] as $field) {
            if ($this->has($field) && ($this->input($field) === '' || $this->input($field) === null)) {
                $this->merge([
                    $field => null,
                ]);
            }
        }

        // Clean empty optional phone to null
        if ($this->has('phone') && ($this->input('phone') === '' || $this->input('phone') === null)) {
            $this->merge([
                'phone' => null,
            ]);
        }
    }
}
