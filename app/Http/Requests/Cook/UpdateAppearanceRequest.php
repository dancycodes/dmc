<?php

namespace App\Http\Requests\Cook;

use App\Services\TenantThemeService;
use Illuminate\Foundation\Http\FormRequest;

/**
 * F-214: Cook Theme Selection
 *
 * Validates the appearance update request (theme, font, border_radius).
 *
 * BR-520: Valid preset names from TenantThemeService.
 * BR-521: Valid font names from TenantThemeService.
 * BR-522: Valid border radius options from TenantThemeService.
 * BR-526: Only the cook can change theme settings.
 */
class UpdateAppearanceRequest extends FormRequest
{
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
     * @return array<string, array<string>>
     */
    public function rules(): array
    {
        $themeService = app(TenantThemeService::class);
        $validPresets = implode(',', array_keys($themeService->availablePresets()));
        $validFonts = implode(',', array_keys($themeService->availableFonts()));
        $validRadii = implode(',', array_keys($themeService->availableRadii()));

        return [
            'theme' => ['required', 'string', "in:{$validPresets}"],
            'font' => ['required', 'string', "in:{$validFonts}"],
            'border_radius' => ['required', 'string', "in:{$validRadii}"],
        ];
    }

    /**
     * Get custom error messages for validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'theme.required' => __('Please select a theme.'),
            'theme.in' => __('The selected theme is invalid.'),
            'font.required' => __('Please select a font.'),
            'font.in' => __('The selected font is invalid.'),
            'border_radius.required' => __('Please select a border radius.'),
            'border_radius.in' => __('The selected border radius is invalid.'),
        ];
    }
}
