<?php

namespace App\Http\Controllers\Cook;

use App\Http\Controllers\Controller;
use App\Services\SetupWizardService;
use Illuminate\Http\Request;

class SetupWizardController extends Controller
{
    public function __construct(
        private SetupWizardService $wizardService,
    ) {}

    /**
     * Display the setup wizard at the current or requested step.
     *
     * F-071: Cook Setup Wizard Shell
     * BR-108: Wizard has exactly 4 steps.
     * BR-113: Only shown to users with cook or manager role (enforced by cook.access middleware).
     * BR-114: Wizard resumes where the cook left off.
     * BR-116: Cook can still access wizard after going live.
     */
    public function show(Request $request): mixed
    {
        $tenant = tenant();
        $requestedStep = (int) $request->query('step', 0);

        // Determine the active step
        if ($requestedStep > 0 && $this->wizardService->isValidStep($requestedStep)) {
            // Allow navigation to completed steps or the current step
            if ($this->wizardService->isStepNavigable($tenant, $requestedStep)) {
                $activeStep = $requestedStep;
            } else {
                $activeStep = $this->wizardService->getCurrentStep($tenant);
            }
        } else {
            $activeStep = $this->wizardService->getCurrentStep($tenant);
        }

        $steps = $this->wizardService->getStepsData($tenant, $activeStep);
        $requirements = $this->wizardService->getRequirementsSummary($tenant);

        // Handle Gale navigate for step switching within the wizard
        if ($request->isGaleNavigate('wizard-step')) {
            return gale()
                ->fragment('cook.setup.wizard', 'wizard-content', [
                    'tenant' => $tenant,
                    'steps' => $steps,
                    'activeStep' => $activeStep,
                    'requirements' => $requirements,
                    'setupComplete' => $tenant->isSetupComplete(),
                ])
                ->fragment('cook.setup.wizard', 'wizard-progress', [
                    'tenant' => $tenant,
                    'steps' => $steps,
                    'activeStep' => $activeStep,
                    'requirements' => $requirements,
                    'setupComplete' => $tenant->isSetupComplete(),
                ]);
        }

        return gale()->view('cook.setup.wizard', [
            'tenant' => $tenant,
            'steps' => $steps,
            'activeStep' => $activeStep,
            'requirements' => $requirements,
            'setupComplete' => $tenant->isSetupComplete(),
        ], web: true);
    }

    /**
     * Handle the "Go Live" action.
     *
     * BR-109: Minimum setup requirements must be met.
     * BR-111: "Go Live" button only enabled when requirements are met.
     * BR-115: Sets tenant setup_complete flag to true.
     */
    public function goLive(Request $request): mixed
    {
        $tenant = tenant();

        // Verify minimum requirements are met
        if (! $this->wizardService->canGoLive($tenant)) {
            if ($request->isGale()) {
                return gale()->state('goLiveError', __('Please complete all minimum requirements before going live.'));
            }

            return redirect()->route('cook.setup')->with('error', __('Please complete all minimum requirements before going live.'));
        }

        // Mark setup as complete
        $this->wizardService->goLive($tenant);

        // Log the activity
        activity('tenants')
            ->performedOn($tenant)
            ->causedBy($request->user())
            ->withProperties(['action' => 'go_live'])
            ->log('Tenant setup completed — went live');

        if ($request->isGale()) {
            return gale()->redirect(url('/dashboard'))->with('success', __('Congratulations! Your store is now live.'));
        }

        return redirect()->route('cook.dashboard')->with('success', __('Congratulations! Your store is now live.'));
    }
}
