<?php

namespace App\Http\Controllers\Cook;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Cook\UpdateBrandInfoRequest;
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
        // Any valid step can be viewed when explicitly requested via URL.
        // The progress bar controls navigability (links vs plain text per BR-112).
        // The Continue/Skip buttons always advance to the next step.
        if ($requestedStep > 0 && $this->wizardService->isValidStep($requestedStep)) {
            $activeStep = $requestedStep;
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
     * Save brand info (Step 1).
     *
     * F-072: Brand Info Step
     * BR-117: Brand name required in both EN and FR.
     * BR-118: If bio provided in one language, must be in both.
     * BR-119: WhatsApp number required, valid Cameroon format.
     * BR-120: Phone optional but valid Cameroon format if provided.
     * BR-121 to BR-124: Social links optional, valid URL, max lengths.
     * BR-125: Step complete when name (both) and WhatsApp are saved.
     */
    public function saveBrandInfo(Request $request): mixed
    {
        $tenant = tenant();

        // Dual Gale/HTTP validation pattern
        if ($request->isGale()) {
            // Normalize phone numbers in JSON body before validateState reads them
            $rawWhatsapp = (string) $request->state('whatsapp', '');
            $rawPhone = (string) $request->state('phone', '');

            if ($rawWhatsapp !== '') {
                $request->json()->set('whatsapp', RegisterRequest::normalizePhone($rawWhatsapp));
            }

            if ($rawPhone !== '') {
                $request->json()->set('phone', RegisterRequest::normalizePhone($rawPhone));
            }

            // Trim text fields in JSON body
            foreach (['name_en', 'name_fr', 'description_en', 'description_fr'] as $field) {
                $value = $request->state($field, '');
                if (is_string($value)) {
                    $request->json()->set($field, trim($value));
                }
            }

            // Clean empty social links to null
            foreach (['social_facebook', 'social_instagram', 'social_tiktok'] as $field) {
                $value = $request->state($field, '');
                if ($value === '' || $value === null) {
                    $request->json()->set($field, null);
                }
            }

            // Clean empty optional phone to null
            if ($rawPhone === '' || $rawPhone === null) {
                $request->json()->set('phone', null);
            }

            $validated = $request->validateState([
                'name_en' => ['required', 'string', 'max:100'],
                'name_fr' => ['required', 'string', 'max:100'],
                'description_en' => ['nullable', 'string', 'max:1000'],
                'description_fr' => ['nullable', 'string', 'max:1000'],
                'whatsapp' => ['required', 'string', 'regex:'.UpdateBrandInfoRequest::CAMEROON_PHONE_REGEX],
                'phone' => ['nullable', 'string', 'regex:'.UpdateBrandInfoRequest::CAMEROON_PHONE_REGEX],
                'social_facebook' => ['nullable', 'string', 'url', 'max:500'],
                'social_instagram' => ['nullable', 'string', 'url', 'max:500'],
                'social_tiktok' => ['nullable', 'string', 'url', 'max:500'],
            ], [
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
            ]);

            // BR-118: If bio provided in one language, must be in both
            $descEn = $validated['description_en'] ?? '';
            $descFr = $validated['description_fr'] ?? '';
            $hasEn = ! empty($descEn) && trim($descEn) !== '';
            $hasFr = ! empty($descFr) && trim($descFr) !== '';

            if ($hasEn && ! $hasFr) {
                return gale()->state('errors', [
                    'description_fr' => [__('Bio is required in French when provided in English.')],
                ]);
            }

            if ($hasFr && ! $hasEn) {
                return gale()->state('errors', [
                    'description_en' => [__('Bio is required in English when provided in French.')],
                ]);
            }
        } else {
            $formRequest = app(UpdateBrandInfoRequest::class);
            $validated = $formRequest->validated();
        }

        // Track old values for activity logging
        $oldValues = $tenant->only([
            'name_en', 'name_fr', 'description_en', 'description_fr',
            'whatsapp', 'phone', 'social_facebook', 'social_instagram', 'social_tiktok',
        ]);

        // Update tenant brand info
        $tenant->update([
            'name_en' => $validated['name_en'],
            'name_fr' => $validated['name_fr'],
            'description_en' => $validated['description_en'] ?? null,
            'description_fr' => $validated['description_fr'] ?? null,
            'whatsapp' => $validated['whatsapp'],
            'phone' => $validated['phone'] ?? null,
            'social_facebook' => $validated['social_facebook'] ?? null,
            'social_instagram' => $validated['social_instagram'] ?? null,
            'social_tiktok' => $validated['social_tiktok'] ?? null,
        ]);

        // BR-125: Mark step 1 as complete
        $this->wizardService->markStepComplete($tenant, 1);

        // Activity logging with old/new comparison
        $newValues = $tenant->only([
            'name_en', 'name_fr', 'description_en', 'description_fr',
            'whatsapp', 'phone', 'social_facebook', 'social_instagram', 'social_tiktok',
        ]);

        $changes = array_filter($newValues, function ($value, $key) use ($oldValues) {
            return ($oldValues[$key] ?? null) !== $value;
        }, ARRAY_FILTER_USE_BOTH);

        if (! empty($changes)) {
            activity('tenants')
                ->performedOn($tenant)
                ->causedBy($request->user())
                ->withProperties([
                    'action' => 'brand_info_updated',
                    'old' => array_intersect_key($oldValues, $changes),
                    'new' => $changes,
                ])
                ->log('Brand info updated via setup wizard');
        }

        if ($request->isGale()) {
            return gale()
                ->redirect(url('/dashboard/setup?step=2'))
                ->with('success', __('Brand info saved successfully.'));
        }

        return redirect()->route('cook.setup', ['step' => 2])
            ->with('success', __('Brand info saved successfully.'));
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
