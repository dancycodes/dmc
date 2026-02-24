<?php

namespace App\Http\Controllers;

use App\Services\ProfilePhotoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProfilePhotoController extends Controller
{
    public function __construct(
        private readonly ProfilePhotoService $photoService
    ) {}

    /**
     * Display the profile photo upload page.
     *
     * BR-097: Only accessible to authenticated users (enforced by 'auth' middleware).
     * BR-100: Accessible from any domain (main or tenant).
     */
    public function show(Request $request): mixed
    {
        $user = Auth::user();

        return gale()->view('profile.photo', [
            'user' => $user,
            'tenant' => tenant(),
        ], web: true);
    }

    /**
     * Upload and save a new profile photo.
     *
     * Uses $request->validate() (not validateState) because Gale auto-converts
     * to multipart FormData when x-files are present (lesson from F-183).
     *
     * BR-103: Accepted file formats: JPG (JPEG), PNG, WebP only.
     * BR-104: Maximum file size: 2MB.
     * BR-105: Uploaded images are resized/cropped to 256×256 pixels, square.
     * BR-107: Old photo deleted when new one is uploaded.
     * BR-110: Photo updates reflected via Gale without page reload.
     * BR-111: Activity logged on upload.
     */
    public function upload(Request $request): mixed
    {
        $user = Auth::user();

        // Use $request->validate() — not validateState() — because Gale sends
        // multipart FormData when x-files is used (F-183 lesson).
        $validated = $request->validate([
            'photo' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ], [
            'photo.required' => __('Please select a photo to upload.'),
            'photo.file' => __('The photo must be a valid file.'),
            'photo.image' => __('The photo must be a JPG, PNG, or WebP image.'),
            'photo.mimes' => __('The photo must be a JPG, PNG, or WebP image.'),
            'photo.max' => __('The photo must be smaller than 2MB.'),
        ]);

        $file = $request->file('photo');

        // BR-107: Delete old photo before saving new one
        $oldPath = $user->profile_photo_path;

        // Process and store the new photo
        $newPath = $this->photoService->processAndStore($file);

        // Update user record
        $user->update(['profile_photo_path' => $newPath]);

        // BR-107: Remove old file from storage after successful update
        if ($oldPath) {
            $this->photoService->delete($oldPath);
        }

        // BR-111: Log upload activity
        activity('users')
            ->performedOn($user)
            ->causedBy($user)
            ->event('photo_uploaded')
            ->withProperties([
                'old_path' => $oldPath,
                'new_path' => $newPath,
                'ip' => $request->ip(),
            ])
            ->log(__('Profile photo was uploaded'));

        return gale()->redirect('/profile/photo')
            ->with('toast', [
                'type' => 'success',
                'message' => __('Profile photo updated.'),
            ]);
    }

    /**
     * Remove the user's profile photo.
     *
     * BR-108: Removing a photo reverts the display to a default avatar.
     * BR-111: Activity logged on remove.
     */
    public function destroy(Request $request): mixed
    {
        $user = Auth::user();

        if (! $user->profile_photo_path) {
            return gale()->redirect('/profile/photo')
                ->with('toast', [
                    'type' => 'info',
                    'message' => __('No profile photo to remove.'),
                ]);
        }

        $oldPath = $user->profile_photo_path;

        // Remove from storage
        $this->photoService->delete($oldPath);

        // Clear the path on the user record
        $user->update(['profile_photo_path' => null]);

        // BR-111: Log removal activity
        activity('users')
            ->performedOn($user)
            ->causedBy($user)
            ->event('photo_removed')
            ->withProperties([
                'old_path' => $oldPath,
                'ip' => $request->ip(),
            ])
            ->log(__('Profile photo was removed'));

        return gale()->redirect('/profile/photo')
            ->with('toast', [
                'type' => 'success',
                'message' => __('Profile photo removed.'),
            ]);
    }
}
