<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

class ProfilePhotoService
{
    /**
     * The storage disk to use for profile photos.
     */
    public const DISK = 'public';

    /**
     * The directory within the disk to store photos.
     */
    public const DIRECTORY = 'photos/users';

    /**
     * The target dimension for the square crop/resize in pixels.
     */
    public const DIMENSION = 256;

    /**
     * Upload and process a user's profile photo.
     *
     * Resizes/crops the image to a 256×256 square using center-crop strategy,
     * stores it in the public disk under photos/users/, and returns the path.
     *
     * BR-103: Accepted file formats: JPG (JPEG), PNG, WebP only.
     * BR-104: Maximum file size: 2MB (enforced in form request).
     * BR-105: Uploaded images are resized/cropped to 256×256 pixels, square.
     * BR-109: Uses Intervention Image v3.
     */
    public function processAndStore(UploadedFile $file): string
    {
        $manager = new ImageManager(new Driver);
        $image = $manager->read($file->getRealPath());

        // Center-crop to square then resize to 256×256 (BR-105)
        $image->cover(self::DIMENSION, self::DIMENSION);

        // Generate a unique filename with the correct extension
        $extension = strtolower($file->getClientOriginalExtension());

        // Normalise webp extension
        if ($extension === 'webp') {
            $extension = 'webp';
        } else {
            $extension = 'jpg';
        }

        $filename = self::DIRECTORY.'/'.uniqid('user_', true).'.'.$extension;

        // Encode and save to the public disk
        $encoded = $image->toJpeg(90);

        if ($extension === 'webp') {
            $encoded = $image->toWebp(90);
        }

        Storage::disk(self::DISK)->put($filename, $encoded->toString());

        return $filename;
    }

    /**
     * Delete a stored profile photo by its path.
     *
     * BR-107: When a new photo replaces an old one, the old file must be deleted.
     */
    public function delete(string $path): void
    {
        if (Storage::disk(self::DISK)->exists($path)) {
            Storage::disk(self::DISK)->delete($path);
        }
    }
}
