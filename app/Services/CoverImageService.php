<?php

namespace App\Services;

use App\Models\Tenant;
use Illuminate\Http\UploadedFile;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class CoverImageService
{
    /**
     * The media collection name for cover images.
     */
    public const COLLECTION = 'cover-images';

    /**
     * Maximum number of cover images per tenant.
     *
     * BR-127: Maximum 5 cover images per cook.
     */
    public const MAX_IMAGES = 5;

    /**
     * Maximum file size in kilobytes (2MB).
     *
     * BR-129: Maximum file size: 2MB per image.
     */
    public const MAX_FILE_SIZE_KB = 2048;

    /**
     * Accepted MIME types.
     *
     * BR-128: Accepted formats: JPEG, PNG, WebP.
     */
    public const ACCEPTED_MIMES = ['image/jpeg', 'image/png', 'image/webp'];

    /**
     * Accepted file extensions for validation messages.
     */
    public const ACCEPTED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp'];

    /**
     * Get all cover images for a tenant, ordered by position.
     *
     * BR-131: Image order determines carousel display order.
     *
     * @return \Illuminate\Support\Collection<int, Media>
     */
    public function getImages(Tenant $tenant): \Illuminate\Support\Collection
    {
        return $tenant->getMedia(self::COLLECTION)
            ->sortBy('order_column')
            ->values();
    }

    /**
     * Get the current image count for a tenant.
     */
    public function getImageCount(Tenant $tenant): int
    {
        return $tenant->getMedia(self::COLLECTION)->count();
    }

    /**
     * Check if the tenant can upload more images.
     *
     * BR-127: Maximum 5 cover images.
     */
    public function canUploadMore(Tenant $tenant, int $additionalCount = 1): bool
    {
        return ($this->getImageCount($tenant) + $additionalCount) <= self::MAX_IMAGES;
    }

    /**
     * Upload cover images for a tenant.
     *
     * BR-127: Maximum 5 cover images per cook.
     * BR-128: Accepted formats: JPEG, PNG, WebP.
     * BR-129: Maximum file size: 2MB per image.
     * BR-130: Images are resized to 16:9 aspect ratio.
     * BR-134: Stored via Spatie Media Library.
     *
     * @param  array<UploadedFile>  $files
     * @return array{uploaded: array<Media>, errors: array<string>}
     */
    public function uploadImages(Tenant $tenant, array $files): array
    {
        $uploaded = [];
        $errors = [];

        foreach ($files as $file) {
            if (! $this->canUploadMore($tenant)) {
                $errors[] = __('Maximum :count images allowed.', ['count' => self::MAX_IMAGES]);

                break;
            }

            try {
                $media = $tenant->addMedia($file)
                    ->toMediaCollection(self::COLLECTION);

                $uploaded[] = $media;
            } catch (\Exception $e) {
                $errors[] = __('Unable to process image: :name', ['name' => $file->getClientOriginalName()]);
            }
        }

        return [
            'uploaded' => $uploaded,
            'errors' => $errors,
        ];
    }

    /**
     * Reorder cover images by media IDs.
     *
     * BR-131: Image order determines carousel display order; first image is primary.
     * BR-133: Reorder works on both desktop and mobile.
     *
     * @param  array<int>  $orderedIds
     */
    public function reorderImages(Tenant $tenant, array $orderedIds): bool
    {
        $existingMedia = $tenant->getMedia(self::COLLECTION);
        $existingIds = $existingMedia->pluck('id')->toArray();

        // Validate all IDs belong to this tenant's collection
        foreach ($orderedIds as $id) {
            if (! in_array((int) $id, $existingIds, true)) {
                return false;
            }
        }

        // Use Spatie's setNewOrder
        Media::setNewOrder($orderedIds);

        return true;
    }

    /**
     * Delete a specific cover image.
     *
     * BR-135: Deleting an image requires confirmation (handled in UI).
     */
    public function deleteImage(Tenant $tenant, int $mediaId): bool
    {
        $media = $tenant->getMedia(self::COLLECTION)
            ->firstWhere('id', $mediaId);

        if (! $media) {
            return false;
        }

        $media->delete();

        return true;
    }

    /**
     * Get image data formatted for the frontend.
     *
     * @return array<int, array{id: int, url: string, thumbnail: string, name: string, size: int, order: int}>
     */
    public function getImagesData(Tenant $tenant): array
    {
        return $this->getImages($tenant)->map(function (Media $media) {
            return [
                'id' => $media->id,
                'url' => $media->getUrl('carousel'),
                'thumbnail' => $media->getUrl('thumbnail'),
                'name' => $media->file_name,
                'size' => $media->size,
                'order' => $media->order_column,
            ];
        })->toArray();
    }

    /**
     * Check if step 2 has at least one cover image (for step completion tracking).
     *
     * BR-132: This step is optional — not required for minimum setup.
     */
    public function hasCoverImages(Tenant $tenant): bool
    {
        return $this->getImageCount($tenant) > 0;
    }
}
