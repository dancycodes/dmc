<?php

namespace App\Http\Controllers\Cook;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Cook\UpdateBrandInfoRequest;
use App\Services\CoverImageService;
use App\Services\SetupWizardService;
use Illuminate\Http\Request;

class SetupWizardController extends Controller
{
    public function __construct(
        private SetupWizardService $wizardService,
        private CoverImageService $coverImageService,
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

        $viewData = [
            'tenant' => $tenant,
            'steps' => $steps,
            'activeStep' => $activeStep,
            'requirements' => $requirements,
            'setupComplete' => $tenant->isSetupComplete(),
            'coverImages' => $coverImages,
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
