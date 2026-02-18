<?php

namespace App\Services;

use App\Models\Meal;
use App\Models\MealImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

class MealImageService
{
    /**
     * The storage disk for meal images.
     */
    public const DISK = 'public';

    /**
     * The directory for processed images.
     */
    public const IMAGE_DIR = 'meal-images';

    /**
     * The directory for thumbnails.
     */
    public const THUMB_DIR = 'meal-images/thumbs';

    /**
     * Get all images for a meal, ordered by position.
     *
     * BR-203: Images can be reordered via drag-and-drop; order determines carousel sequence.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, MealImage>
     */
    public function getImages(Meal $meal): \Illuminate\Database\Eloquent\Collection
    {
        return $meal->images()->ordered()->get();
    }

    /**
     * Get the current image count for a meal.
     */
    public function getImageCount(Meal $meal): int
    {
        return $meal->images()->count();
    }

    /**
     * Check if the meal can accept more images.
     *
     * BR-198: Maximum 3 images per meal.
     */
    public function canUploadMore(Meal $meal): bool
    {
        return $this->getImageCount($meal) < MealImage::MAX_IMAGES;
    }

    /**
     * Get the remaining upload slots for a meal.
     */
    public function getRemainingSlots(Meal $meal): int
    {
        return max(0, MealImage::MAX_IMAGES - $this->getImageCount($meal));
    }

    /**
     * Upload and process a meal image.
     *
     * BR-199: Accepted formats: jpg/jpeg, png, webp
     * BR-200: Maximum file size: 2MB per image
     * BR-201: Images are resized/optimized on upload (maintain aspect ratio)
     * BR-202: A thumbnail version is generated for meal cards
     *
     * @return array{success: bool, image?: MealImage, error?: string}
     */
    public function uploadImage(Meal $meal, UploadedFile $file): array
    {
        if (! $this->canUploadMore($meal)) {
            return [
                'success' => false,
                'error' => __('Maximum :count images allowed.', ['count' => MealImage::MAX_IMAGES]),
            ];
        }

        try {
            $manager = new ImageManager(new Driver);

            // Generate unique filename
            $extension = $file->getClientOriginalExtension() ?: 'jpg';
            if ($extension === 'jpeg') {
                $extension = 'jpg';
            }
            $filename = uniqid('meal_', true).'.'.$extension;

            // Process main image (resize, maintain aspect ratio)
            $image = $manager->read($file->getPathname());
            $image->scaleDown(MealImage::MAX_WIDTH, MealImage::MAX_HEIGHT);

            // Determine encoding format
            $encodedImage = $this->encodeImage($image, $extension);

            // Store processed image
            $imagePath = self::IMAGE_DIR.'/'.$filename;
            Storage::disk(self::DISK)->put($imagePath, $encodedImage);

            // Generate and store thumbnail
            $thumbnail = $manager->read($file->getPathname());
            $thumbnail->cover(MealImage::THUMB_WIDTH, MealImage::THUMB_HEIGHT);
            $encodedThumb = $this->encodeImage($thumbnail, $extension);

            $thumbPath = self::THUMB_DIR.'/'.$filename;
            Storage::disk(self::DISK)->put($thumbPath, $encodedThumb);

            // Determine next position
            $nextPosition = ($meal->images()->max('position') ?? -1) + 1;

            // Create database record
            $mealImage = MealImage::create([
                'meal_id' => $meal->id,
                'path' => $imagePath,
                'thumbnail_path' => $thumbPath,
                'position' => $nextPosition,
                'original_filename' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'file_size' => $file->getSize(),
            ]);

            return [
                'success' => true,
                'image' => $mealImage,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => __('Unable to process image: :name', ['name' => $file->getClientOriginalName()]),
            ];
        }
    }

    /**
     * Reorder images by their IDs.
     *
     * BR-203: Images can be reordered via drag-and-drop; order determines carousel sequence.
     * BR-204: First image in order is the primary/hero image shown on meal cards.
     *
     * @param  array<int>  $orderedIds
     */
    public function reorderImages(Meal $meal, array $orderedIds): bool
    {
        $existingIds = $meal->images()->pluck('id')->toArray();

        // Validate all IDs belong to this meal
        foreach ($orderedIds as $id) {
            if (! in_array((int) $id, $existingIds, true)) {
                return false;
            }
        }

        // Update positions
        foreach ($orderedIds as $position => $id) {
            MealImage::where('id', $id)
                ->where('meal_id', $meal->id)
                ->update(['position' => $position]);
        }

        return true;
    }

    /**
     * Delete a meal image and its files.
     *
     * BR-205: Individual images can be deleted with confirmation.
     *
     * @return array{success: bool, error?: string}
     */
    public function deleteImage(Meal $meal, int $imageId): array
    {
        $image = $meal->images()->find($imageId);

        if (! $image) {
            return [
                'success' => false,
                'error' => __('Image not found or already deleted.'),
            ];
        }

        // Delete files from storage
        if ($image->path && Storage::disk(self::DISK)->exists($image->path)) {
            Storage::disk(self::DISK)->delete($image->path);
        }

        if ($image->thumbnail_path && Storage::disk(self::DISK)->exists($image->thumbnail_path)) {
            Storage::disk(self::DISK)->delete($image->thumbnail_path);
        }

        $image->delete();

        // Reorder remaining images to fill gaps
        $this->normalizePositions($meal);

        return ['success' => true];
    }

    /**
     * Get image data formatted for the frontend.
     *
     * @return array<int, array{id: int, url: string, thumbnail: string, name: string, size: int, formattedSize: string, position: int}>
     */
    public function getImagesData(Meal $meal): array
    {
        return $this->getImages($meal)->map(function (MealImage $image) {
            return [
                'id' => $image->id,
                'url' => $image->url,
                'thumbnail' => $image->thumbnail_url,
                'name' => $image->original_filename,
                'size' => $image->file_size,
                'formattedSize' => $image->formatted_size,
                'position' => $image->position,
            ];
        })->toArray();
    }

    /**
     * Normalize positions to be sequential (0, 1, 2...).
     */
    private function normalizePositions(Meal $meal): void
    {
        $images = $meal->images()->ordered()->get();

        foreach ($images as $index => $image) {
            if ($image->position !== $index) {
                $image->update(['position' => $index]);
            }
        }
    }

    /**
     * Encode an Intervention Image instance to the appropriate format.
     */
    private function encodeImage(\Intervention\Image\Image $image, string $extension): string
    {
        return match ($extension) {
            'png' => $image->toPng()->toString(),
            'webp' => $image->toWebp(quality: 85)->toString(),
            default => $image->toJpeg(quality: 85)->toString(),
        };
    }
}
