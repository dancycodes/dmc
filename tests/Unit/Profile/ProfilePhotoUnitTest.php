<?php

use App\Http\Controllers\ProfilePhotoController;
use App\Http\Requests\Profile\UploadPhotoRequest;
use App\Services\ProfilePhotoService;

/*
|--------------------------------------------------------------------------
| F-031: Profile Photo Upload — Unit Tests
|--------------------------------------------------------------------------
|
| Pure unit tests covering class existence, constants, method presence,
| validation rules, and request messages without requiring a full app context.
|
*/

$projectRoot = dirname(__DIR__, 3);

// ProfilePhotoService — class and constants

it('has a ProfilePhotoService class', function () {
    expect(class_exists(ProfilePhotoService::class))->toBeTrue();
});

it('ProfilePhotoService has DISK constant set to public', function () {
    expect(ProfilePhotoService::DISK)->toBe('public');
});

it('ProfilePhotoService has DIRECTORY constant set to photos/users', function () {
    expect(ProfilePhotoService::DIRECTORY)->toBe('photos/users');
});

it('ProfilePhotoService has DIMENSION constant set to 256', function () {
    expect(ProfilePhotoService::DIMENSION)->toBe(256);
});

it('ProfilePhotoService has processAndStore method', function () {
    expect(method_exists(ProfilePhotoService::class, 'processAndStore'))->toBeTrue();
});

it('ProfilePhotoService has delete method', function () {
    expect(method_exists(ProfilePhotoService::class, 'delete'))->toBeTrue();
});

// ProfilePhotoController — class and methods

it('has a ProfilePhotoController class', function () {
    expect(class_exists(ProfilePhotoController::class))->toBeTrue();
});

it('ProfilePhotoController has show method', function () {
    expect(method_exists(ProfilePhotoController::class, 'show'))->toBeTrue();
});

it('ProfilePhotoController has upload method', function () {
    expect(method_exists(ProfilePhotoController::class, 'upload'))->toBeTrue();
});

it('ProfilePhotoController has destroy method', function () {
    expect(method_exists(ProfilePhotoController::class, 'destroy'))->toBeTrue();
});

// UploadPhotoRequest — validation rules

it('has an UploadPhotoRequest class', function () {
    expect(class_exists(UploadPhotoRequest::class))->toBeTrue();
});

it('UploadPhotoRequest rules include required file validation', function () {
    $request = new UploadPhotoRequest;
    $rules = $request->rules();

    expect($rules)->toHaveKey('photo');
    expect($rules['photo'])->toContain('required');
    expect($rules['photo'])->toContain('file');
    expect($rules['photo'])->toContain('image');
});

it('UploadPhotoRequest rules enforce max 2MB', function () {
    $request = new UploadPhotoRequest;
    $rules = $request->rules();

    expect($rules['photo'])->toContain('max:2048');
});

it('UploadPhotoRequest rules enforce accepted mime types', function () {
    $request = new UploadPhotoRequest;
    $rules = $request->rules();

    expect($rules['photo'])->toContain('mimes:jpg,jpeg,png,webp');
});

it('UploadPhotoRequest messages method returns an array with photo.max key', function () {
    // Inspect the source so we don't need the translator service in unit context
    $reflection = new ReflectionClass(UploadPhotoRequest::class);
    $source = file_get_contents($reflection->getFileName());

    expect($source)->toContain("'photo.max'");
});

it('UploadPhotoRequest messages method returns an array with photo.mimes key', function () {
    $reflection = new ReflectionClass(UploadPhotoRequest::class);
    $source = file_get_contents($reflection->getFileName());

    expect($source)->toContain("'photo.mimes'");
});

// View file existence

it('has the profile photo blade view file', function () use ($projectRoot) {
    $viewPath = $projectRoot.'/resources/views/profile/photo.blade.php';
    expect(file_exists($viewPath))->toBeTrue();
});

// Route file contains photo routes

it('web routes include GET /profile/photo', function () use ($projectRoot) {
    $routesPath = $projectRoot.'/routes/web.php';
    $contents = file_get_contents($routesPath);

    expect($contents)->toContain('/profile/photo');
    expect($contents)->toContain('ProfilePhotoController');
});

it('web routes include DELETE /profile/photo for photo removal', function () use ($projectRoot) {
    $routesPath = $projectRoot.'/routes/web.php';
    $contents = file_get_contents($routesPath);

    expect($contents)->toContain("Route::delete('/profile/photo'");
});

// Nav layouts include photo support

it('main-public layout renders photo conditionally', function () use ($projectRoot) {
    $layoutPath = $projectRoot.'/resources/views/layouts/main-public.blade.php';
    $contents = file_get_contents($layoutPath);

    expect($contents)->toContain('profile_photo_path');
    expect($contents)->toContain("asset('storage/'");
});

it('tenant-public layout renders photo conditionally', function () use ($projectRoot) {
    $layoutPath = $projectRoot.'/resources/views/layouts/tenant-public.blade.php';
    $contents = file_get_contents($layoutPath);

    expect($contents)->toContain('profile_photo_path');
    expect($contents)->toContain("asset('storage/'");
});
