<?php

namespace App\Http\Controllers\Cook;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cook\UpdateAppearanceRequest;
use App\Http\Requests\Cook\UpdateCancellationWindowRequest;
use App\Http\Requests\Cook\UpdateMinimumOrderRequest;
use App\Services\CookSettingsService;
use App\Services\TenantThemeService;
use Illuminate\Http\Request;

/**
 * F-212: Cancellation Window Configuration
 * F-213: Minimum Order Amount Configuration
 * F-214: Cook Theme Selection
 *
 * Handles the cook settings page within the cook dashboard.
 * Hosts cancellation window, minimum order amount, and appearance configurations.
 *
 * BR-503: Only the cook can modify cancellation window (not managers).
 * BR-515: Only the cook can modify minimum order amount (not managers).
 * BR-526: Only the cook can change theme settings (not managers).
 * BR-506: Gale handles the setting form interaction without page reloads.
 * BR-532: Gale handles all preview and save interactions without page reloads.
 */
class CookSettingsController extends Controller
{
    public function __construct(
        private CookSettingsService $settingsService,
        private TenantThemeService $themeService,
    ) {}

    /**
     * Display the cook settings page.
     *
     * BR-499: Shows current cancellation window value.
     * BR-494: Default is 15 minutes if not set.
     * BR-507: Shows current minimum order amount; default 0 XAF.
     * BR-525: Shows current theme, font, border_radius from tenant settings.
     */
    public function index(Request $request): mixed
    {
        $tenant = tenant();
        $cancellationWindowMinutes = $this->settingsService->getCancellationWindow($tenant);
        $minimumOrderAmount = $this->settingsService->getMinimumOrderAmount($tenant);
        $appearance = $this->settingsService->getAppearance($tenant);

        $availablePresets = $this->themeService->availablePresets();
        $availableFonts = $this->themeService->availableFonts();
        $availableRadii = $this->themeService->availableRadii();

        $presetColors = $this->getPresetSwatchColors();

        return gale()->view('cook.settings.index', compact(
            'cancellationWindowMinutes',
            'minimumOrderAmount',
            'appearance',
            'availablePresets',
            'availableFonts',
            'availableRadii',
            'presetColors',
        ), web: true);
    }

    /**
     * Update the cancellation window for the current tenant.
     *
     * BR-495: Validates range 5–120 minutes.
     * BR-496: Must be integer.
     * BR-497: Applies to all new orders from the moment saved.
     * BR-503: Only cook (not manager) can modify — enforced by cook_reserved nav + COOK_RESERVED_PATHS.
     * BR-504: Changes logged via Spatie Activitylog.
     * BR-506: Gale handles without page reload.
     */
    public function updateCancellationWindow(Request $request): mixed
    {
        if ($request->isGale()) {
            $validated = $request->validateState([
                'cancellation_window_minutes' => [
                    'required',
                    'integer',
                    'min:'.CookSettingsService::MIN_CANCELLATION_WINDOW,
                    'max:'.CookSettingsService::MAX_CANCELLATION_WINDOW,
                ],
            ]);
        } else {
            $validated = app(UpdateCancellationWindowRequest::class)->validated();
        }

        $tenant = tenant();
        $this->settingsService->updateCancellationWindow(
            $tenant,
            (int) $validated['cancellation_window_minutes'],
            $request->user(),
        );

        if ($request->isGale()) {
            return gale()
                ->state('cancellation_saved', true)
                ->dispatch('toast', [
                    'type' => 'success',
                    'message' => __('Cancellation window saved successfully.'),
                ]);
        }

        return gale()->redirect(route('cook.settings.index'));
    }

    /**
     * Update the minimum order amount for the current tenant.
     *
     * BR-508: Validates range 0–100,000 XAF.
     * BR-509: Must be integer (whole number).
     * BR-515: Only cook (not manager) can modify — enforced by cook_reserved nav + COOK_RESERVED_PATHS.
     * BR-517: Changes logged via Spatie Activitylog.
     */
    public function updateMinimumOrderAmount(Request $request): mixed
    {
        if ($request->isGale()) {
            $validated = $request->validateState([
                'minimum_order_amount' => [
                    'required',
                    'integer',
                    'min:'.CookSettingsService::MIN_ORDER_AMOUNT,
                    'max:'.CookSettingsService::MAX_ORDER_AMOUNT,
                ],
            ]);
        } else {
            $validated = app(UpdateMinimumOrderRequest::class)->validated();
        }

        $tenant = tenant();
        $this->settingsService->updateMinimumOrderAmount(
            $tenant,
            (int) $validated['minimum_order_amount'],
            $request->user(),
        );

        if ($request->isGale()) {
            return gale()
                ->state('minimum_saved', true)
                ->dispatch('toast', [
                    'type' => 'success',
                    'message' => __('Minimum order amount saved successfully.'),
                ]);
        }

        return gale()->redirect(route('cook.settings.index'));
    }

    /**
     * Update the appearance settings for the current tenant.
     *
     * BR-520: Valid theme presets only.
     * BR-521: Valid font families only.
     * BR-522: Valid border radius options only.
     * BR-524: Changes apply to tenant domain immediately on save.
     * BR-526: Only cook (not manager) — enforced by COOK_RESERVED_PATHS.
     * BR-530: Changes logged via Spatie Activitylog with old/new values.
     * BR-532: Gale handles save without page reload.
     */
    public function updateAppearance(Request $request): mixed
    {
        if ($request->isGale()) {
            $themeService = $this->themeService;
            $validPresets = implode(',', array_keys($themeService->availablePresets()));
            $validFonts = implode(',', array_keys($themeService->availableFonts()));
            $validRadii = implode(',', array_keys($themeService->availableRadii()));

            $validated = $request->validateState([
                'theme' => ['required', 'string', "in:{$validPresets}"],
                'font' => ['required', 'string', "in:{$validFonts}"],
                'border_radius' => ['required', 'string', "in:{$validRadii}"],
            ]);
        } else {
            $validated = app(UpdateAppearanceRequest::class)->validated();
        }

        $tenant = tenant();
        $this->settingsService->updateAppearance(
            $tenant,
            $validated['theme'],
            $validated['font'],
            $validated['border_radius'],
            $request->user(),
        );

        if ($request->isGale()) {
            return gale()
                ->state('appearance_saved', true)
                ->dispatch('toast', [
                    'type' => 'success',
                    'message' => __('Appearance saved successfully.'),
                ]);
        }

        return gale()->redirect(route('cook.settings.index'));
    }

    /**
     * Reset the appearance to DancyMeals defaults.
     *
     * BR-527: Reset to Default = Modern + Inter + medium.
     * BR-532: Gale handles reset without page reload.
     */
    public function resetAppearance(Request $request): mixed
    {
        $tenant = tenant();
        $defaults = $this->settingsService->resetAppearance($tenant, $request->user());

        if ($request->isGale()) {
            return gale()
                ->state('theme', $defaults['new']['theme'])
                ->state('font', $defaults['new']['font'])
                ->state('border_radius', $defaults['new']['border_radius'])
                ->state('appearance_saved', false)
                ->dispatch('toast', [
                    'type' => 'success',
                    'message' => __('Appearance reset to defaults.'),
                ]);
        }

        return gale()->redirect(route('cook.settings.index'));
    }

    /**
     * Get the primary swatch colors for each preset for visual display.
     *
     * Returns two colors per preset (primary + secondary) for the swatch circles.
     *
     * @return array<string, array{primary: string, secondary: string, on_primary: string}>
     */
    private function getPresetSwatchColors(): array
    {
        $presets = config('tenant-themes.presets', []);
        $swatches = [];

        foreach ($presets as $key => $preset) {
            $light = $preset['light'] ?? [];
            $swatches[$key] = [
                'primary' => $light['--color-primary'] ?? '#0D9488',
                'secondary' => $light['--color-secondary'] ?? '#F59E0B',
                'primary_subtle' => $light['--color-primary-subtle'] ?? '#F0FDFA',
                'on_primary' => $light['--color-on-primary'] ?? '#FFFFFF',
            ];
        }

        return $swatches;
    }
}
