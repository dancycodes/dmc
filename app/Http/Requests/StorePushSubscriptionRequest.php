<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePushSubscriptionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'endpoint' => ['required', 'string', 'url', 'max:500'],
            'keys' => ['required', 'array'],
            'keys.p256dh' => ['required', 'string'],
            'keys.auth' => ['required', 'string'],
            'contentEncoding' => ['nullable', 'string', 'in:aesgcm,aes128gcm'],
        ];
    }

    /**
     * Get custom error messages for validation.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'endpoint.required' => __('A valid push subscription endpoint is required.'),
            'endpoint.url' => __('The push subscription endpoint must be a valid URL.'),
            'keys.required' => __('Push subscription keys are required.'),
            'keys.p256dh.required' => __('The p256dh key is required for push subscriptions.'),
            'keys.auth.required' => __('The auth key is required for push subscriptions.'),
        ];
    }
}
