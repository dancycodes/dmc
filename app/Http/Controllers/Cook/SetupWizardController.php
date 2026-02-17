<?php

namespace App\Http\Controllers\Cook;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Cook\UpdateBrandInfoRequest;
use App\Services\CoverImageService;
use App\Services\DeliveryAreaService;
use App\Services\SetupWizardService;
use Illuminate\Http\Request;

class SetupWizardController extends Controller
{
    public function __construct(
        private SetupWizardService $wizardService,
        private CoverImageService $coverImageService,
        private DeliveryAreaService $deliveryAreaService,
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

        // Load cover image data for step 2
        $coverImages = $activeStep === 2
            ? $this->coverImageService->getImagesData($tenant)
            : [];

        // Load delivery area data for step 3
        $deliveryAreas = $activeStep === 3
            ? $this->deliveryAreaService->getDeliveryAreasData($tenant)
            : [];
        $pickupLocations = $activeStep === 3
            ? $this->deliveryAreaService->getPickupLocationsData($tenant)
            : [];

        // Load schedule & meal data for step 4
        $scheduleData = $activeStep === 4
            ? $this->wizardService->getScheduleData($tenant)
            : [];
        $mealsData = $activeStep === 4
            ? $this->wizardService->getMealsData($tenant)
            : [];

        $viewData = [
            'tenant' => $tenant,
            'steps' => $steps,
            'activeStep' => $activeStep,
            'requirements' => $requirements,
            'setupComplete' => $tenant->isSetupComplete(),
            'coverImages' => $coverImages,
            'deliveryAreas' => $deliveryAreas,
            'pickupLocations' => $pickupLocations,
            'scheduleData' => $scheduleData,
            'mealsData' => $mealsData,
        ];

        // Handle Gale navigate for step switching within the wizard
        if ($request->isGaleNavigate('wizard-step')) {
            return gale()
                ->fragment('cook.setup.wizard', 'wizard-content', $viewData)
                ->fragment('cook.setup.wizard', 'wizard-progress', $viewData);
        }

        return gale()->view('cook.setup.wizard', $viewData, web: true);
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
                return gale()->messages([
                    'description_fr' => __('Bio is required in French when provided in English.'),
                ]);
            }

            if ($hasFr && ! $hasEn) {
                return gale()->messages([
                    'description_en' => __('Bio is required in English when provided in French.'),
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
     * Upload cover images (Step 2).
     *
     * F-073: Cover Images Step
     * BR-127: Maximum 5 cover images per cook.
     * BR-128: Accepted formats: JPEG, PNG, WebP.
     * BR-129: Maximum file size: 2MB per image.
     * BR-130: Images resized to 16:9 aspect ratio (handled by media conversions).
     * BR-134: Stored via Spatie Media Library.
     */
    public function uploadCoverImages(Request $request): mixed
    {
        $tenant = tenant();

        // File validation uses standard $request->validate() per Gale docs
        // (files come via FormData, not Alpine state)
        $request->validate([
            'images' => ['required', 'array', 'min:1'],
            'images.*' => [
                'required',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:'.CoverImageService::MAX_FILE_SIZE_KB,
            ],
        ], [
            'images.required' => __('Please select at least one image to upload.'),
            'images.*.image' => __('Only JPG, PNG, and WebP images are accepted.'),
            'images.*.mimes' => __('Only JPG, PNG, and WebP images are accepted.'),
            'images.*.max' => __('Image must be under 2MB.'),
        ]);

        $files = $request->file('images', []);
        $currentCount = $this->coverImageService->getImageCount($tenant);
        $remainingSlots = CoverImageService::MAX_IMAGES - $currentCount;

        if ($remainingSlots <= 0) {
            if ($request->isGale()) {
                return gale()->messages([
                    'images' => __('Maximum :count images allowed.', ['count' => CoverImageService::MAX_IMAGES]),
                ]);
            }

            return redirect()->back()->withErrors([
                'images' => __('Maximum :count images allowed.', ['count' => CoverImageService::MAX_IMAGES]),
            ]);
        }

        // Limit files to remaining slots
        $filesToUpload = array_slice($files, 0, $remainingSlots);

        $result = $this->coverImageService->uploadImages($tenant, $filesToUpload);

        // Mark step as complete if images were uploaded successfully
        if (! empty($result['uploaded'])) {
            $this->wizardService->markStepComplete($tenant, 2);

            // Activity logging
            activity('tenants')
                ->performedOn($tenant)
                ->causedBy($request->user())
                ->withProperties([
                    'action' => 'cover_images_uploaded',
                    'count' => count($result['uploaded']),
                    'total' => $this->coverImageService->getImageCount($tenant),
                ])
                ->log('Cover images uploaded via setup wizard');
        }

        if ($request->isGale()) {
            $images = $this->coverImageService->getImagesData($tenant);
            $imageCount = $this->coverImageService->getImageCount($tenant);

            $response = gale()
                ->state('images', $images)
                ->state('imageCount', $imageCount)
                ->state('canUploadMore', $imageCount < CoverImageService::MAX_IMAGES);

            if (! empty($result['errors'])) {
                $response->state('uploadErrors', $result['errors']);
            } else {
                $response->state('uploadErrors', []);
            }

            return $response;
        }

        $flashMsg = ! empty($result['errors'])
            ? __('Some images could not be uploaded.')
            : __('Images uploaded successfully.');

        return redirect()->route('cook.setup', ['step' => 2])->with('success', $flashMsg);
    }

    /**
     * Reorder cover images (Step 2).
     *
     * F-073: Cover Images Step
     * BR-131: Image order determines carousel display order.
     * BR-133: Drag-to-reorder works on desktop and mobile.
     */
    public function reorderCoverImages(Request $request): mixed
    {
        $tenant = tenant();

        if ($request->isGale()) {
            $validated = $request->validateState([
                'orderedIds' => ['required', 'array', 'min:1'],
                'orderedIds.*' => ['required', 'integer'],
            ], [
                'orderedIds.required' => __('Image order data is required.'),
            ]);
        } else {
            $validated = $request->validate([
                'orderedIds' => ['required', 'array', 'min:1'],
                'orderedIds.*' => ['required', 'integer'],
            ]);
        }

        $success = $this->coverImageService->reorderImages($tenant, $validated['orderedIds']);

        if (! $success) {
            if ($request->isGale()) {
                return gale()->messages([
                    'reorder' => __('Unable to reorder images. Please try again.'),
                ]);
            }

            return redirect()->back()->withErrors([
                'reorder' => __('Unable to reorder images. Please try again.'),
            ]);
        }

        // Activity logging
        activity('tenants')
            ->performedOn($tenant)
            ->causedBy($request->user())
            ->withProperties([
                'action' => 'cover_images_reordered',
                'order' => $validated['orderedIds'],
            ])
            ->log('Cover images reordered via setup wizard');

        if ($request->isGale()) {
            $images = $this->coverImageService->getImagesData($tenant);

            return gale()
                ->state('images', $images)
                ->state('reorderSuccess', true);
        }

        return redirect()->route('cook.setup', ['step' => 2])->with('success', __('Image order updated.'));
    }

    /**
     * Delete a cover image (Step 2).
     *
     * F-073: Cover Images Step
     * BR-135: Deleting an image requires confirmation (handled in UI).
     */
    public function deleteCoverImage(Request $request, int $mediaId): mixed
    {
        $tenant = tenant();

        $success = $this->coverImageService->deleteImage($tenant, $mediaId);

        if (! $success) {
            if ($request->isGale()) {
                return gale()->messages([
                    'delete' => __('Image not found or already deleted.'),
                ]);
            }

            return redirect()->back()->withErrors([
                'delete' => __('Image not found or already deleted.'),
            ]);
        }

        // Refresh the media relationship to get updated count
        $tenant->load('media');
        $imageCount = $this->coverImageService->getImageCount($tenant);

        // If no images remain, un-mark step 2 as complete
        if ($imageCount === 0) {
            $completedSteps = $tenant->getSetting('setup_steps', []);
            $completedSteps = array_values(array_filter($completedSteps, fn ($s) => $s !== 2));
            $tenant->setSetting('setup_steps', $completedSteps);
            $tenant->save();
        }

        // Activity logging
        activity('tenants')
            ->performedOn($tenant)
            ->causedBy($request->user())
            ->withProperties([
                'action' => 'cover_image_deleted',
                'media_id' => $mediaId,
                'remaining' => $imageCount,
            ])
            ->log('Cover image deleted via setup wizard');

        if ($request->isGale()) {
            $images = $this->coverImageService->getImagesData($tenant);

            return gale()
                ->state('images', $images)
                ->state('imageCount', $imageCount)
                ->state('canUploadMore', $imageCount < CoverImageService::MAX_IMAGES)
                ->remove('#cover-image-'.$mediaId);
        }

        return redirect()->route('cook.setup', ['step' => 2])->with('success', __('Image deleted.'));
    }

    /**
     * Add a town to the cook's delivery areas (Step 3).
     *
     * F-074: Delivery Areas Step
     * BR-137: Town name required in EN and FR.
     * BR-138: Town name must be unique within this cook's towns.
     */
    public function addTown(Request $request): mixed
    {
        $tenant = tenant();

        if ($request->isGale()) {
            $validated = $request->validateState([
                'town_name_en' => ['required', 'string', 'max:255'],
                'town_name_fr' => ['required', 'string', 'max:255'],
            ], [
                'town_name_en.required' => __('Town name is required in English.'),
                'town_name_fr.required' => __('Town name is required in French.'),
            ]);
        } else {
            $validated = $request->validate([
                'town_name_en' => ['required', 'string', 'max:255'],
                'town_name_fr' => ['required', 'string', 'max:255'],
            ]);
        }

        $result = $this->deliveryAreaService->addTown(
            $tenant,
            trim($validated['town_name_en']),
            trim($validated['town_name_fr']),
        );

        if (! $result['success']) {
            if ($request->isGale()) {
                return gale()->messages([
                    'town_name_en' => $result['error'],
                ]);
            }

            return redirect()->back()->withErrors(['town_name_en' => $result['error']]);
        }

        // Activity logging
        activity('tenants')
            ->performedOn($tenant)
            ->causedBy($request->user())
            ->withProperties([
                'action' => 'delivery_town_added',
                'town_id' => $result['delivery_area']->town_id,
                'town_name' => $validated['town_name_en'],
            ])
            ->log('Delivery town added via setup wizard');

        if ($request->isGale()) {
            $deliveryAreas = $this->deliveryAreaService->getDeliveryAreasData($tenant);
            $hasMinimum = $this->deliveryAreaService->hasMinimumSetup($tenant);

            return gale()
                ->state('deliveryAreas', $deliveryAreas)
                ->state('hasMinimumSetup', $hasMinimum)
                ->state('town_name_en', '')
                ->state('town_name_fr', '');
        }

        return redirect()->route('cook.setup', ['step' => 3])
            ->with('success', __('Town added successfully.'));
    }

    /**
     * Remove a town from the cook's delivery areas (Step 3).
     *
     * F-074: Delivery Areas Step
     */
    public function removeTown(Request $request, int $deliveryAreaId): mixed
    {
        $tenant = tenant();

        $success = $this->deliveryAreaService->removeTown($tenant, $deliveryAreaId);

        if (! $success) {
            if ($request->isGale()) {
                return gale()->messages([
                    'town' => __('Town not found or already removed.'),
                ]);
            }

            return redirect()->back()->withErrors(['town' => __('Town not found or already removed.')]);
        }

        // Check if step should be un-marked
        $hasMinimum = $this->deliveryAreaService->hasMinimumSetup($tenant);
        if (! $hasMinimum) {
            $completedSteps = $tenant->getSetting('setup_steps', []);
            $completedSteps = array_values(array_filter($completedSteps, fn ($s) => $s !== 3));
            $tenant->setSetting('setup_steps', $completedSteps);
            $tenant->save();
        }

        // Activity logging
        activity('tenants')
            ->performedOn($tenant)
            ->causedBy($request->user())
            ->withProperties([
                'action' => 'delivery_town_removed',
                'delivery_area_id' => $deliveryAreaId,
            ])
            ->log('Delivery town removed via setup wizard');

        if ($request->isGale()) {
            $deliveryAreas = $this->deliveryAreaService->getDeliveryAreasData($tenant);

            return gale()
                ->state('deliveryAreas', $deliveryAreas)
                ->state('hasMinimumSetup', $hasMinimum);
        }

        return redirect()->route('cook.setup', ['step' => 3])
            ->with('success', __('Town removed.'));
    }

    /**
     * Add a quarter to a delivery area (Step 3).
     *
     * F-074: Delivery Areas Step
     * BR-139: Quarter name required in EN and FR.
     * BR-140: Quarter name must be unique within its parent town.
     * BR-141: Delivery fee >= 0 XAF.
     * BR-142: Delivery fee stored as integer.
     */
    public function addQuarter(Request $request, int $deliveryAreaId): mixed
    {
        $tenant = tenant();

        if ($request->isGale()) {
            $validated = $request->validateState([
                'quarter_name_en' => ['required', 'string', 'max:255'],
                'quarter_name_fr' => ['required', 'string', 'max:255'],
                'delivery_fee' => ['required', 'integer', 'min:0'],
            ], [
                'quarter_name_en.required' => __('Quarter name is required in English.'),
                'quarter_name_fr.required' => __('Quarter name is required in French.'),
                'delivery_fee.required' => __('Delivery fee is required.'),
                'delivery_fee.integer' => __('Delivery fee must be a whole number.'),
                'delivery_fee.min' => __('Delivery fee cannot be negative.'),
            ]);
        } else {
            $validated = $request->validate([
                'quarter_name_en' => ['required', 'string', 'max:255'],
                'quarter_name_fr' => ['required', 'string', 'max:255'],
                'delivery_fee' => ['required', 'integer', 'min:0'],
            ]);
        }

        $result = $this->deliveryAreaService->addQuarter(
            $tenant,
            $deliveryAreaId,
            trim($validated['quarter_name_en']),
            trim($validated['quarter_name_fr']),
            (int) $validated['delivery_fee'],
        );

        if (! $result['success']) {
            if ($request->isGale()) {
                return gale()->messages([
                    'quarter_name_en' => $result['error'],
                ]);
            }

            return redirect()->back()->withErrors(['quarter_name_en' => $result['error']]);
        }

        // Mark step as complete if minimum setup is met
        $hasMinimum = $this->deliveryAreaService->hasMinimumSetup($tenant);
        if ($hasMinimum) {
            $this->wizardService->markStepComplete($tenant, 3);
        }

        // Activity logging
        activity('tenants')
            ->performedOn($tenant)
            ->causedBy($request->user())
            ->withProperties([
                'action' => 'delivery_quarter_added',
                'delivery_area_id' => $deliveryAreaId,
                'quarter_name' => $validated['quarter_name_en'],
                'delivery_fee' => $validated['delivery_fee'],
            ])
            ->log('Delivery quarter added via setup wizard');

        if ($request->isGale()) {
            $deliveryAreas = $this->deliveryAreaService->getDeliveryAreasData($tenant);

            $response = gale()
                ->state('deliveryAreas', $deliveryAreas)
                ->state('hasMinimumSetup', $hasMinimum)
                ->state('quarter_name_en', '')
                ->state('quarter_name_fr', '')
                ->state('delivery_fee', 0);

            if (! empty($result['warning'])) {
                $response->state('feeWarning', $result['warning']);
            } else {
                $response->state('feeWarning', '');
            }

            return $response;
        }

        return redirect()->route('cook.setup', ['step' => 3])
            ->with('success', __('Quarter added successfully.'));
    }

    /**
     * Remove a quarter from a delivery area (Step 3).
     *
     * F-074: Delivery Areas Step
     */
    public function removeQuarter(Request $request, int $deliveryAreaQuarterId): mixed
    {
        $tenant = tenant();

        $success = $this->deliveryAreaService->removeQuarter($tenant, $deliveryAreaQuarterId);

        if (! $success) {
            if ($request->isGale()) {
                return gale()->messages([
                    'quarter' => __('Quarter not found or already removed.'),
                ]);
            }

            return redirect()->back()->withErrors(['quarter' => __('Quarter not found or already removed.')]);
        }

        // Check if step should be un-marked
        $hasMinimum = $this->deliveryAreaService->hasMinimumSetup($tenant);
        if (! $hasMinimum) {
            $completedSteps = $tenant->getSetting('setup_steps', []);
            $completedSteps = array_values(array_filter($completedSteps, fn ($s) => $s !== 3));
            $tenant->setSetting('setup_steps', $completedSteps);
            $tenant->save();
        }

        // Activity logging
        activity('tenants')
            ->performedOn($tenant)
            ->causedBy($request->user())
            ->withProperties([
                'action' => 'delivery_quarter_removed',
                'delivery_area_quarter_id' => $deliveryAreaQuarterId,
            ])
            ->log('Delivery quarter removed via setup wizard');

        if ($request->isGale()) {
            $deliveryAreas = $this->deliveryAreaService->getDeliveryAreasData($tenant);

            return gale()
                ->state('deliveryAreas', $deliveryAreas)
                ->state('hasMinimumSetup', $hasMinimum);
        }

        return redirect()->route('cook.setup', ['step' => 3])
            ->with('success', __('Quarter removed.'));
    }

    /**
     * Add a pickup location (Step 3).
     *
     * F-074: Delivery Areas Step
     * BR-143: Pickup locations are optional.
     */
    public function addPickupLocation(Request $request): mixed
    {
        $tenant = tenant();

        if ($request->isGale()) {
            $validated = $request->validateState([
                'pickup_name_en' => ['required', 'string', 'max:255'],
                'pickup_name_fr' => ['required', 'string', 'max:255'],
                'pickup_town_id' => ['required', 'integer', 'exists:towns,id'],
                'pickup_quarter_id' => ['required', 'integer', 'exists:quarters,id'],
                'pickup_address' => ['required', 'string', 'max:500'],
            ], [
                'pickup_name_en.required' => __('Location name is required in English.'),
                'pickup_name_fr.required' => __('Location name is required in French.'),
                'pickup_town_id.required' => __('Please select a town.'),
                'pickup_quarter_id.required' => __('Please select a quarter.'),
                'pickup_address.required' => __('Address is required.'),
            ]);
        } else {
            $validated = $request->validate([
                'pickup_name_en' => ['required', 'string', 'max:255'],
                'pickup_name_fr' => ['required', 'string', 'max:255'],
                'pickup_town_id' => ['required', 'integer', 'exists:towns,id'],
                'pickup_quarter_id' => ['required', 'integer', 'exists:quarters,id'],
                'pickup_address' => ['required', 'string', 'max:500'],
            ]);
        }

        $result = $this->deliveryAreaService->addPickupLocation(
            $tenant,
            trim($validated['pickup_name_en']),
            trim($validated['pickup_name_fr']),
            (int) $validated['pickup_town_id'],
            (int) $validated['pickup_quarter_id'],
            trim($validated['pickup_address']),
        );

        if (! $result['success']) {
            if ($request->isGale()) {
                return gale()->messages([
                    'pickup_town_id' => $result['error'],
                ]);
            }

            return redirect()->back()->withErrors(['pickup_town_id' => $result['error']]);
        }

        // Activity logging
        activity('tenants')
            ->performedOn($tenant)
            ->causedBy($request->user())
            ->withProperties([
                'action' => 'pickup_location_added',
                'pickup_name' => $validated['pickup_name_en'],
            ])
            ->log('Pickup location added via setup wizard');

        if ($request->isGale()) {
            $pickupLocations = $this->deliveryAreaService->getPickupLocationsData($tenant);

            return gale()
                ->state('pickupLocations', $pickupLocations)
                ->state('pickup_name_en', '')
                ->state('pickup_name_fr', '')
                ->state('pickup_town_id', '')
                ->state('pickup_quarter_id', '')
                ->state('pickup_address', '');
        }

        return redirect()->route('cook.setup', ['step' => 3])
            ->with('success', __('Pickup location added.'));
    }

    /**
     * Remove a pickup location (Step 3).
     *
     * F-074: Delivery Areas Step
     */
    public function removePickupLocation(Request $request, int $pickupLocationId): mixed
    {
        $tenant = tenant();

        $success = $this->deliveryAreaService->removePickupLocation($tenant, $pickupLocationId);

        if (! $success) {
            if ($request->isGale()) {
                return gale()->messages([
                    'pickup' => __('Pickup location not found or already removed.'),
                ]);
            }

            return redirect()->back()->withErrors(['pickup' => __('Pickup location not found or already removed.')]);
        }

        // Activity logging
        activity('tenants')
            ->performedOn($tenant)
            ->causedBy($request->user())
            ->withProperties([
                'action' => 'pickup_location_removed',
                'pickup_location_id' => $pickupLocationId,
            ])
            ->log('Pickup location removed via setup wizard');

        if ($request->isGale()) {
            $pickupLocations = $this->deliveryAreaService->getPickupLocationsData($tenant);

            return gale()
                ->state('pickupLocations', $pickupLocations);
        }

        return redirect()->route('cook.setup', ['step' => 3])
            ->with('success', __('Pickup location removed.'));
    }

    /**
     * Save delivery areas and continue to next step (Step 3).
     *
     * F-074: Delivery Areas Step
     * BR-136: At least 1 town with 1 quarter and delivery fee is required.
     */
    public function saveDeliveryAreas(Request $request): mixed
    {
        $tenant = tenant();

        if (! $this->deliveryAreaService->hasMinimumSetup($tenant)) {
            if ($request->isGale()) {
                return gale()->messages([
                    'delivery_areas' => __('Please add at least one town with one quarter and a delivery fee before continuing.'),
                ]);
            }

            return redirect()->back()->withErrors([
                'delivery_areas' => __('Please add at least one town with one quarter and a delivery fee before continuing.'),
            ]);
        }

        // Mark step 3 as complete
        $this->wizardService->markStepComplete($tenant, 3);

        // Activity logging
        activity('tenants')
            ->performedOn($tenant)
            ->causedBy($request->user())
            ->withProperties(['action' => 'delivery_areas_setup_completed'])
            ->log('Delivery areas setup completed via wizard');

        if ($request->isGale()) {
            return gale()
                ->redirect(url('/dashboard/setup?step=4'))
                ->with('success', __('Delivery areas saved successfully.'));
        }

        return redirect()->route('cook.setup', ['step' => 4])
            ->with('success', __('Delivery areas saved successfully.'));
    }

    /**
     * Save schedule data (Step 4).
     *
     * F-075: Schedule & First Meal Step
     * BR-146: Schedule sets availability per day of week with start and end times.
     * BR-152: Schedule times use 24-hour format.
     * BR-153: End time must be after start time.
     * BR-154: Uses the same schedules table as the full schedule features.
     */
    public function saveSchedule(Request $request): mixed
    {
        $tenant = tenant();

        if ($request->isGale()) {
            $validated = $request->validateState([
                'scheduleData' => ['required', 'array'],
                'scheduleData.*.day' => ['required', 'integer', 'between:0,6'],
                'scheduleData.*.enabled' => ['required', 'boolean'],
                'scheduleData.*.start_time' => ['required_if:scheduleData.*.enabled,true', 'nullable', 'date_format:H:i'],
                'scheduleData.*.end_time' => ['required_if:scheduleData.*.enabled,true', 'nullable', 'date_format:H:i'],
            ], [
                'scheduleData.*.start_time.date_format' => __('Please use a valid time format (HH:MM).'),
                'scheduleData.*.end_time.date_format' => __('Please use a valid time format (HH:MM).'),
            ]);
            $scheduleEntries = $validated['scheduleData'];
        } else {
            $validated = $request->validate([
                'schedule' => ['required', 'array'],
                'schedule.*.day' => ['required', 'integer', 'between:0,6'],
                'schedule.*.enabled' => ['required', 'boolean'],
                'schedule.*.start_time' => ['required_if:schedule.*.enabled,true', 'nullable', 'date_format:H:i'],
                'schedule.*.end_time' => ['required_if:schedule.*.enabled,true', 'nullable', 'date_format:H:i'],
            ]);
            $scheduleEntries = $validated['schedule'];
        }

        // BR-153: Validate end time > start time for enabled days
        foreach ($scheduleEntries as $entry) {
            if ($entry['enabled'] && ! empty($entry['start_time']) && ! empty($entry['end_time'])) {
                if ($entry['end_time'] <= $entry['start_time']) {
                    if ($request->isGale()) {
                        $dayLabel = \App\Models\Schedule::DAY_LABELS[$entry['day']] ?? 'Unknown';

                        return gale()->messages([
                            'schedule' => __('End time must be after start time for :day.', ['day' => $dayLabel]),
                        ]);
                    }

                    return redirect()->back()->withErrors([
                        'schedule' => __('End time must be after start time.'),
                    ]);
                }
            }
        }

        // Save schedule: delete existing, insert enabled ones
        $tenant->schedules()->delete();

        $enabledDays = [];
        foreach ($scheduleEntries as $entry) {
            if ($entry['enabled'] && ! empty($entry['start_time']) && ! empty($entry['end_time'])) {
                $tenant->schedules()->create([
                    'day_of_week' => $entry['day'],
                    'start_time' => $entry['start_time'],
                    'end_time' => $entry['end_time'],
                    'is_available' => true,
                ]);
                $enabledDays[] = \App\Models\Schedule::DAY_LABELS[$entry['day']] ?? $entry['day'];
            }
        }

        // Activity logging
        activity('tenants')
            ->performedOn($tenant)
            ->causedBy($request->user())
            ->withProperties([
                'action' => 'schedule_saved',
                'enabled_days' => $enabledDays,
                'total_days' => count($enabledDays),
            ])
            ->log('Schedule saved via setup wizard');

        if ($request->isGale()) {
            $scheduleData = $this->wizardService->getScheduleData($tenant);

            return gale()
                ->state('scheduleData', $scheduleData)
                ->state('scheduleSaved', true)
                ->state('hasSchedule', count($enabledDays) > 0);
        }

        return redirect()->route('cook.setup', ['step' => 4])
            ->with('success', __('Schedule saved successfully.'));
    }

    /**
     * Save a meal with components (Step 4).
     *
     * F-075: Schedule & First Meal Step
     * BR-147: At least one meal with at least one component is required.
     * BR-148: Meal name required in both English and French.
     * BR-149: Meal price must be > 0 XAF.
     * BR-150: Each meal must have at least one component (name required in en/fr).
     * BR-151: Created meals default to is_active = true.
     * BR-155: Step 4 is complete when at least one active meal with one component exists.
     */
    public function saveMeal(Request $request): mixed
    {
        $tenant = tenant();

        if ($request->isGale()) {
            $validated = $request->validateState([
                'meal_name_en' => ['required', 'string', 'max:255'],
                'meal_name_fr' => ['required', 'string', 'max:255'],
                'meal_description_en' => ['nullable', 'string', 'max:2000'],
                'meal_description_fr' => ['nullable', 'string', 'max:2000'],
                'meal_price' => ['required', 'integer', 'min:1'],
                'components' => ['required', 'array', 'min:1'],
                'components.*.name_en' => ['required', 'string', 'max:255'],
                'components.*.name_fr' => ['required', 'string', 'max:255'],
            ], [
                'meal_name_en.required' => __('Meal name is required in English.'),
                'meal_name_fr.required' => __('Meal name is required in French.'),
                'meal_price.required' => __('Price is required.'),
                'meal_price.integer' => __('Price must be a whole number.'),
                'meal_price.min' => __('Price must be greater than 0 XAF.'),
                'components.required' => __('At least one component is required.'),
                'components.min' => __('At least one component is required.'),
                'components.*.name_en.required' => __('Component name is required in English.'),
                'components.*.name_fr.required' => __('Component name is required in French.'),
            ]);
        } else {
            $validated = $request->validate([
                'meal_name_en' => ['required', 'string', 'max:255'],
                'meal_name_fr' => ['required', 'string', 'max:255'],
                'meal_description_en' => ['nullable', 'string', 'max:2000'],
                'meal_description_fr' => ['nullable', 'string', 'max:2000'],
                'meal_price' => ['required', 'integer', 'min:1'],
                'components' => ['required', 'array', 'min:1'],
                'components.*.name_en' => ['required', 'string', 'max:255'],
                'components.*.name_fr' => ['required', 'string', 'max:255'],
            ]);
        }

        // Create the meal (BR-151: defaults to is_active = true)
        $meal = $tenant->meals()->create([
            'name_en' => trim($validated['meal_name_en']),
            'name_fr' => trim($validated['meal_name_fr']),
            'description_en' => ! empty($validated['meal_description_en']) ? trim($validated['meal_description_en']) : null,
            'description_fr' => ! empty($validated['meal_description_fr']) ? trim($validated['meal_description_fr']) : null,
            'price' => (int) $validated['meal_price'],
            'is_active' => true,
        ]);

        // Create the components
        foreach ($validated['components'] as $componentData) {
            $meal->components()->create([
                'name_en' => trim($componentData['name_en']),
                'name_fr' => trim($componentData['name_fr']),
                'description_en' => null,
                'description_fr' => null,
            ]);
        }

        // BR-155: Mark step 4 as complete when at least one active meal with component exists
        if ($this->wizardService->hasActiveMeal($tenant)) {
            $this->wizardService->markStepComplete($tenant, 4);
        }

        // Activity logging
        activity('meals')
            ->performedOn($meal)
            ->causedBy($request->user())
            ->withProperties([
                'action' => 'meal_created',
                'meal_name' => $meal->name_en,
                'price' => $meal->price,
                'component_count' => count($validated['components']),
            ])
            ->log('Meal created via setup wizard');

        if ($request->isGale()) {
            $mealsData = $this->wizardService->getMealsData($tenant);
            $requirements = $this->wizardService->getRequirementsSummary($tenant);

            return gale()
                ->state('mealsData', $mealsData)
                ->state('hasMeal', true)
                ->state('canGoLive', $requirements['can_go_live'])
                ->state('meal_name_en', '')
                ->state('meal_name_fr', '')
                ->state('meal_description_en', '')
                ->state('meal_description_fr', '')
                ->state('meal_price', '')
                ->state('components', [['name_en' => '', 'name_fr' => '']]);
        }

        return redirect()->route('cook.setup', ['step' => 4])
            ->with('success', __('Meal created successfully.'));
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
