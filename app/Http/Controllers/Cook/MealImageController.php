<?php

namespace App\Http\Controllers\Cook;

use App\Http\Controllers\Controller;
use App\Models\MealImage;
use App\Services\MealImageService;
use Illuminate\Http\Request;

class MealImageController extends Controller
{
    public function __construct(
        private MealImageService $imageService,
    ) {}

    /**
     * Upload images for a meal.
     *
     * F-109: Meal Image Upload & Carousel
     * BR-198: Maximum 3 images per meal
     * BR-199: Accepted formats: jpg/jpeg, png, webp
     * BR-200: Maximum file size: 2MB per image
     * BR-201: Images are resized/optimized on upload
     * BR-202: A thumbnail version is generated for meal cards
     * BR-207: Only users with can-manage-meals permission
     * BR-208: Image upload is logged via Spatie Activitylog
     */
    public function upload(Request $request, int $mealId): mixed
    {
        $tenant = tenant();
        $user = $request->user();

        // BR-207: Permission check
        if (! $user->can('can-manage-meals')) {
            abort(403);
        }

        $meal = $tenant->meals()->findOrFail($mealId);

        // File validation (files come via FormData, not Alpine state)
        $request->validate([
            'images' => ['required', 'array', 'min:1'],
            'images.*' => [
                'required',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:'.MealImage::MAX_FILE_SIZE_KB,
            ],
        ], [
            'images.required' => __('Please select at least one image to upload.'),
            'images.*.image' => __('Only JPG, PNG, and WebP images are accepted.'),
            'images.*.mimes' => __('Only JPG, PNG, and WebP images are accepted.'),
            'images.*.max' => __('Image must be 2MB or smaller.'),
        ]);

        $files = $request->file('images', []);
        $remainingSlots = $this->imageService->getRemainingSlots($meal);

        if ($remainingSlots <= 0) {
            if ($request->isGale()) {
                return gale()->messages([
                    'images' => __('Maximum :count images reached.', ['count' => MealImage::MAX_IMAGES]),
                ]);
            }

            return redirect()->back()->withErrors([
                'images' => __('Maximum :count images reached.', ['count' => MealImage::MAX_IMAGES]),
            ]);
        }

        // Limit files to remaining slots
        $filesToUpload = array_slice($files, 0, $remainingSlots);
        $uploaded = [];
        $errors = [];

        foreach ($filesToUpload as $file) {
            $result = $this->imageService->uploadImage($meal, $file);

            if ($result['success']) {
                $uploaded[] = $result['image'];
            } else {
                $errors[] = $result['error'];
            }
        }

        // BR-208: Activity logging
        if (! empty($uploaded)) {
            activity('meals')
                ->performedOn($meal)
                ->causedBy($user)
                ->withProperties([
                    'action' => 'meal_images_uploaded',
                    'count' => count($uploaded),
                    'total' => $this->imageService->getImageCount($meal),
                    'tenant_id' => $tenant->id,
                ])
                ->log('Meal images uploaded');
        }

        if ($request->isGale()) {
            $images = $this->imageService->getImagesData($meal);
            $imageCount = $this->imageService->getImageCount($meal);

            $response = gale()
                ->state('images', $images)
                ->state('imageCount', $imageCount)
                ->state('canUploadMore', $imageCount < MealImage::MAX_IMAGES);

            if (! empty($errors)) {
                $response->state('uploadErrors', $errors);
            } else {
                $response->state('uploadErrors', []);
            }

            return $response;
        }

        $flashMsg = ! empty($errors)
            ? __('Some images could not be uploaded.')
            : __('Images uploaded successfully.');

        return redirect()->back()->with('success', $flashMsg);
    }

    /**
     * Reorder meal images.
     *
     * F-109: Meal Image Upload & Carousel
     * BR-203: Images can be reordered via drag-and-drop
     * BR-204: First image in order is the primary/hero image
     * BR-207: Only users with can-manage-meals permission
     * BR-208: Image reorder is logged via Spatie Activitylog
     */
    public function reorder(Request $request, int $mealId): mixed
    {
        $tenant = tenant();
        $user = $request->user();

        // BR-207: Permission check
        if (! $user->can('can-manage-meals')) {
            abort(403);
        }

        $meal = $tenant->meals()->findOrFail($mealId);

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

        $success = $this->imageService->reorderImages($meal, $validated['orderedIds']);

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

        // BR-208: Activity logging
        activity('meals')
            ->performedOn($meal)
            ->causedBy($user)
            ->withProperties([
                'action' => 'meal_images_reordered',
                'order' => $validated['orderedIds'],
                'tenant_id' => $tenant->id,
            ])
            ->log('Meal images reordered');

        if ($request->isGale()) {
            $images = $this->imageService->getImagesData($meal);

            return gale()
                ->state('images', $images)
                ->state('reorderSuccess', true);
        }

        return redirect()->back()->with('success', __('Image order updated.'));
    }

    /**
     * Delete a meal image.
     *
     * F-109: Meal Image Upload & Carousel
     * BR-205: Individual images can be deleted with confirmation
     * BR-207: Only users with can-manage-meals permission
     * BR-208: Image deletion is logged via Spatie Activitylog
     */
    public function destroy(Request $request, int $mealId, int $imageId): mixed
    {
        $tenant = tenant();
        $user = $request->user();

        // BR-207: Permission check
        if (! $user->can('can-manage-meals')) {
            abort(403);
        }

        $meal = $tenant->meals()->findOrFail($mealId);

        $result = $this->imageService->deleteImage($meal, $imageId);

        if (! $result['success']) {
            if ($request->isGale()) {
                return gale()->messages([
                    'delete' => $result['error'],
                ]);
            }

            return redirect()->back()->withErrors([
                'delete' => $result['error'],
            ]);
        }

        $imageCount = $this->imageService->getImageCount($meal);

        // BR-208: Activity logging
        activity('meals')
            ->performedOn($meal)
            ->causedBy($user)
            ->withProperties([
                'action' => 'meal_image_deleted',
                'image_id' => $imageId,
                'remaining' => $imageCount,
                'tenant_id' => $tenant->id,
            ])
            ->log('Meal image deleted');

        if ($request->isGale()) {
            $images = $this->imageService->getImagesData($meal);

            return gale()
                ->state('images', $images)
                ->state('imageCount', $imageCount)
                ->state('canUploadMore', $imageCount < MealImage::MAX_IMAGES)
                ->remove('#meal-image-'.$imageId);
        }

        return redirect()->back()->with('success', __('Image deleted.'));
    }
}
