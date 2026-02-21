<?php

namespace App\Http\Requests\Cook;

use App\Services\CookSettingsService;
use Illuminate\Foundation\Http\FormRequest;

/**
 * F-212: Cancellation Window Configuration
 *
 * Validates the cancellation window value submitted by a cook.
 *
 * BR-495: Allowed range: 5 to 120 minutes (inclusive).
 * BR-496: Value must be a whole number (integer minutes).
 */
class UpdateCancellationWindowRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'cancellation_window_minutes' => [
                'required',
                'integer',
                'min:'.CookSettingsService::MIN_CANCELLATION_WINDOW,
                'max:'.CookSettingsService::MAX_CANCELLATION_WINDOW,
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'cancellation_window_minutes.required' => __('The cancellation window is required.'),
            'cancellation_window_minutes.integer' => __('Cancellation window must be a number.'),
            'cancellation_window_minutes.min' => __('Cancellation window must be between :min and :max minutes.', [
                'min' => CookSettingsService::MIN_CANCELLATION_WINDOW,
                'max' => CookSettingsService::MAX_CANCELLATION_WINDOW,
            ]),
            'cancellation_window_minutes.max' => __('Cancellation window must be between :min and :max minutes.', [
                'min' => CookSettingsService::MIN_CANCELLATION_WINDOW,
                'max' => CookSettingsService::MAX_CANCELLATION_WINDOW,
            ]),
        ];
    }
}
