<?php

namespace App\Http\Controllers\Cook;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cook\UpdateCancellationWindowRequest;
use App\Services\CookSettingsService;
use Illuminate\Http\Request;

/**
 * F-212: Cancellation Window Configuration
 *
 * Handles the cook settings page within the cook dashboard.
 * Currently hosts the cancellation window configuration.
 *
 * BR-503: Only the cook can modify this setting (not managers).
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
     */
    public function index(Request $request): mixed
    {
        $tenant = tenant();
        $cancellationWindowMinutes = $this->settingsService->getCancellationWindow($tenant);

        return gale()->view('cook.settings.index', compact('cancellationWindowMinutes'), web: true);
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

        return gale()
            ->redirect(route('cook.settings.index'))
            ->with('toast', [
                'type' => 'success',
                'message' => __('Cancellation window saved successfully.'),
            ]);
    }
}
