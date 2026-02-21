<?php

namespace App\Http\Controllers\Cook;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cook\UpdateCancellationWindowRequest;
use App\Http\Requests\Cook\UpdateMinimumOrderRequest;
use App\Services\CookSettingsService;
use Illuminate\Http\Request;

/**
 * F-212: Cancellation Window Configuration
 * F-213: Minimum Order Amount Configuration
 *
 * Handles the cook settings page within the cook dashboard.
 * Hosts both cancellation window and minimum order amount configurations.
 *
 * BR-503: Only the cook can modify cancellation window (not managers).
 * BR-515: Only the cook can modify minimum order amount (not managers).
 * BR-506: Gale handles the setting form interaction without page reloads.
 */
class CookSettingsController extends Controller
{
    public function __construct(
        private CookSettingsService $settingsService,
    ) {}

    /**
     * Display the cook settings page.
     *
     * BR-499: Shows current cancellation window value.
     * BR-494: Default is 15 minutes if not set.
     * BR-507: Shows current minimum order amount; default 0 XAF.
     */
    public function index(Request $request): mixed
    {
        $tenant = tenant();
        $cancellationWindowMinutes = $this->settingsService->getCancellationWindow($tenant);
        $minimumOrderAmount = $this->settingsService->getMinimumOrderAmount($tenant);

        return gale()->view('cook.settings.index', compact('cancellationWindowMinutes', 'minimumOrderAmount'), web: true);
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
}
