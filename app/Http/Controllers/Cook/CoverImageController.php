<?php

namespace App\Http\Controllers\Cook;

use App\Http\Controllers\Controller;
use App\Services\CoverImageService;
use Illuminate\Http\Request;

class CoverImageController extends Controller
{
    public function __construct(
        private CoverImageService $coverImageService,
    ) {}

    /**
     * Display the cover images management page.
     *
     * F-081: Cook Cover Images Management
     * BR-197: Maximum 5 cover images per cook
     * BR-201: First image in order is the primary/featured image
     * BR-204: Upload button disabled when 5 images exist
     */
    public function index(Request $request): mixed
    {
        $tenant = tenant();
        $user = $request->user();

        if (! $user->can('can-manage-brand')) {
            abort(403);
        }

        $images = $this->coverImageService->getImagesData($tenant);
        $imageCount = $this->coverImageService->getImageCount($tenant);
        $maxImages = CoverImageService::MAX_IMAGES;

        return gale()->view('cook.profile.cover-images', [
            'tenant' => $tenant,
            'images' => $images,
            'imageCount' => $imageCount,
            'maxImages' => $maxImages,
            'canUploadMore' => $imageCount < $maxImages,
        ], web: true);
    }

    /**
     * Upload cover images.
     *
     * F-081: Cook Cover Images Management
     * BR-197: Maximum 5 cover images per cook.
     * BR-198: Accepted formats: JPEG, PNG, WebP.
     * BR-199: Maximum file size: 2MB per image.
     * BR-200: Images resized to consistent aspect ratio (16:9).
     */
    public function upload(Request $request): mixed
    {
        $tenant = tenant();

        if (! $request->user()->can('can-manage-brand')) {
            abort(403);
        }

        // File validation (files come via FormData, not Alpine state)
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
                    'images' => __('Maximum :count images allowed. Delete one to upload a new one.', ['count' => CoverImageService::MAX_IMAGES]),
                ]);
            }

            return redirect()->back()->withErrors([
                'images' => __('Maximum :count images allowed. Delete one to upload a new one.', ['count' => CoverImageService::MAX_IMAGES]),
            ]);
        }

        // Limit files to remaining slots
        $filesToUpload = array_slice($files, 0, $remainingSlots);

        $result = $this->coverImageService->uploadImages($tenant, $filesToUpload);

        // Activity logging
        if (! empty($result['uploaded'])) {
            activity('tenants')
                ->performedOn($tenant)
                ->causedBy($request->user())
                ->withProperties([
                    'action' => 'cover_images_uploaded',
                    'count' => count($result['uploaded']),
                    'total' => $this->coverImageService->getImageCount($tenant),
                ])
                ->log('Cover images uploaded via profile management');
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

        return redirect()->route('cook.cover-images.index')->with('success', $flashMsg);
    }

    /**
     * Reorder cover images.
     *
     * F-081: Cook Cover Images Management
     * BR-201: First image in order is the primary/featured image.
     * BR-202: Drag-to-reorder saves automatically via Gale.
     * BR-206: Both mouse drag (desktop) and touch drag (mobile) must work.
     */
    public function reorder(Request $request): mixed
    {
        $tenant = tenant();

        if (! $request->user()->can('can-manage-brand')) {
            abort(403);
        }

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
            ->log('Cover images reordered via profile management');

        if ($request->isGale()) {
            $images = $this->coverImageService->getImagesData($tenant);

            return gale()
                ->state('images', $images)
                ->state('reorderSuccess', true);
        }

        return redirect()->route('cook.cover-images.index')->with('success', __('Image order updated.'));
    }

    /**
     * Delete a cover image.
     *
     * F-081: Cook Cover Images Management
     * BR-203: Deleting an image requires confirmation dialog.
     * BR-205: Changes reflect immediately on the tenant landing page and discovery card.
     */
    public function destroy(Request $request, int $mediaId): mixed
    {
        $tenant = tenant();

        if (! $request->user()->can('can-manage-brand')) {
            abort(403);
        }

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

        // Activity logging
        activity('tenants')
            ->performedOn($tenant)
            ->causedBy($request->user())
            ->withProperties([
                'action' => 'cover_image_deleted',
                'media_id' => $mediaId,
                'remaining' => $imageCount,
            ])
            ->log('Cover image deleted via profile management');

        if ($request->isGale()) {
            $images = $this->coverImageService->getImagesData($tenant);

            return gale()
                ->state('images', $images)
                ->state('imageCount', $imageCount)
                ->state('canUploadMore', $imageCount < CoverImageService::MAX_IMAGES)
                ->remove('#cover-image-'.$mediaId);
        }

        return redirect()->route('cook.cover-images.index')->with('success', __('Image deleted.'));
    }
}
