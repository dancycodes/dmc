<?php

use App\Models\Tenant;
use App\Services\CoverImageService;

$projectRoot = dirname(__DIR__, 3);

/*
|--------------------------------------------------------------------------
| F-073: Cover Images Step — Unit Tests
|--------------------------------------------------------------------------
|
| Tests for CoverImageService constants, Tenant media configuration,
| and pure logic that does not require database interaction.
|
*/

// -------------------------------------------------------------------
// CoverImageService Constants
// -------------------------------------------------------------------

describe('CoverImageService constants', function () {
    test('collection name is cover-images', function () {
        expect(CoverImageService::COLLECTION)->toBe('cover-images');
    });

    test('max images is 5 per BR-127', function () {
        expect(CoverImageService::MAX_IMAGES)->toBe(5);
    });

    test('max file size is 2048KB (2MB) per BR-129', function () {
        expect(CoverImageService::MAX_FILE_SIZE_KB)->toBe(2048);
    });

    test('accepted MIME types include JPEG, PNG, and WebP per BR-128', function () {
        expect(CoverImageService::ACCEPTED_MIMES)->toBe([
            'image/jpeg',
            'image/png',
            'image/webp',
        ]);
    });

    test('accepted file extensions match supported formats', function () {
        expect(CoverImageService::ACCEPTED_EXTENSIONS)->toBe([
            'jpg',
            'jpeg',
            'png',
            'webp',
        ]);
    });

    test('max images constant is a positive integer', function () {
        expect(CoverImageService::MAX_IMAGES)->toBeGreaterThan(0);
        expect(CoverImageService::MAX_IMAGES)->toBeInt();
    });

    test('max file size is reasonable for mobile uploads', function () {
        // 2MB = 2048 KB, suitable for mobile photos
        expect(CoverImageService::MAX_FILE_SIZE_KB)->toBe(2048);
        expect(CoverImageService::MAX_FILE_SIZE_KB)->toBeGreaterThan(0);
    });
});

// -------------------------------------------------------------------
// Tenant Media Configuration
// -------------------------------------------------------------------

describe('Tenant media configuration', function () {
    test('tenant model implements HasMedia interface', function () {
        $tenant = new Tenant;
        expect($tenant)->toBeInstanceOf(\Spatie\MediaLibrary\HasMedia::class);
    });

    test('tenant model uses InteractsWithMedia trait', function () {
        $uses = class_uses_recursive(Tenant::class);
        expect($uses)->toContain(\Spatie\MediaLibrary\InteractsWithMedia::class);
    });

    test('tenant has registerMediaCollections method', function () {
        expect(method_exists(Tenant::class, 'registerMediaCollections'))->toBeTrue();
    });

    test('tenant has registerMediaConversions method', function () {
        expect(method_exists(Tenant::class, 'registerMediaConversions'))->toBeTrue();
    });
});

// -------------------------------------------------------------------
// CoverImageService instantiation
// -------------------------------------------------------------------

describe('CoverImageService instantiation', function () {
    test('can be instantiated', function () {
        $service = new CoverImageService;
        expect($service)->toBeInstanceOf(CoverImageService::class);
    });

    test('has getImages method', function () {
        expect(method_exists(CoverImageService::class, 'getImages'))->toBeTrue();
    });

    test('has getImageCount method', function () {
        expect(method_exists(CoverImageService::class, 'getImageCount'))->toBeTrue();
    });

    test('has canUploadMore method', function () {
        expect(method_exists(CoverImageService::class, 'canUploadMore'))->toBeTrue();
    });

    test('has uploadImages method', function () {
        expect(method_exists(CoverImageService::class, 'uploadImages'))->toBeTrue();
    });

    test('has reorderImages method', function () {
        expect(method_exists(CoverImageService::class, 'reorderImages'))->toBeTrue();
    });

    test('has deleteImage method', function () {
        expect(method_exists(CoverImageService::class, 'deleteImage'))->toBeTrue();
    });

    test('has getImagesData method', function () {
        expect(method_exists(CoverImageService::class, 'getImagesData'))->toBeTrue();
    });

    test('has hasCoverImages method', function () {
        expect(method_exists(CoverImageService::class, 'hasCoverImages'))->toBeTrue();
    });
});

// -------------------------------------------------------------------
// Controller Methods
// -------------------------------------------------------------------

describe('SetupWizardController cover image methods', function () {
    test('controller has uploadCoverImages method', function () {
        expect(method_exists(\App\Http\Controllers\Cook\SetupWizardController::class, 'uploadCoverImages'))->toBeTrue();
    });

    test('controller has reorderCoverImages method', function () {
        expect(method_exists(\App\Http\Controllers\Cook\SetupWizardController::class, 'reorderCoverImages'))->toBeTrue();
    });

    test('controller has deleteCoverImage method', function () {
        expect(method_exists(\App\Http\Controllers\Cook\SetupWizardController::class, 'deleteCoverImage'))->toBeTrue();
    });
});

// -------------------------------------------------------------------
// Validation Constants
// -------------------------------------------------------------------

describe('validation constants alignment', function () {
    test('MIME types cover all accepted extensions', function () {
        $mimes = CoverImageService::ACCEPTED_MIMES;
        $extensions = CoverImageService::ACCEPTED_EXTENSIONS;

        // JPEG should be represented by both jpg and jpeg extensions
        expect($mimes)->toContain('image/jpeg');
        expect($extensions)->toContain('jpg');
        expect($extensions)->toContain('jpeg');

        // PNG
        expect($mimes)->toContain('image/png');
        expect($extensions)->toContain('png');

        // WebP
        expect($mimes)->toContain('image/webp');
        expect($extensions)->toContain('webp');
    });

    test('no unsupported formats included', function () {
        $mimes = CoverImageService::ACCEPTED_MIMES;

        expect($mimes)->not->toContain('image/gif');
        expect($mimes)->not->toContain('image/bmp');
        expect($mimes)->not->toContain('image/svg+xml');
        expect($mimes)->not->toContain('application/pdf');
    });
});

// -------------------------------------------------------------------
// Route Verification
// -------------------------------------------------------------------

describe('cover image routes', function () {
    test('blade view file exists', function () {
        $projectRoot = dirname(__DIR__, 3);
        $viewPath = $projectRoot . '/resources/views/cook/setup/steps/cover-images.blade.php';
        expect(file_exists($viewPath))->toBeTrue();
    });

    test('service file exists', function () {
        $projectRoot = dirname(__DIR__, 3);
        $servicePath = $projectRoot . '/app/Services/CoverImageService.php';
        expect(file_exists($servicePath))->toBeTrue();
    });
});

// -------------------------------------------------------------------
// Translation Keys
// -------------------------------------------------------------------

describe('translation keys for cover images', function () {
    test('english translation file contains cover image strings', function () {
        $projectRoot = dirname(__DIR__, 3);
        $enJson = json_decode(file_get_contents($projectRoot . '/lang/en.json'), true);

        expect($enJson)->toHaveKey('Add attractive photos to showcase your food and kitchen.');
        expect($enJson)->toHaveKey('Maximum :count images allowed.');
        expect($enJson)->toHaveKey('Only JPG, PNG, and WebP images are accepted.');
        expect($enJson)->toHaveKey('Image must be under 2MB.');
        expect($enJson)->toHaveKey('Delete Image?');
        expect($enJson)->toHaveKey('Upload Images');
        expect($enJson)->toHaveKey('Primary');
        expect($enJson)->toHaveKey('Preview');
    });

    test('french translation file contains cover image strings', function () {
        $projectRoot = dirname(__DIR__, 3);
        $frJson = json_decode(file_get_contents($projectRoot . '/lang/fr.json'), true);

        expect($frJson)->toHaveKey('Add attractive photos to showcase your food and kitchen.');
        expect($frJson)->toHaveKey('Maximum :count images allowed.');
        expect($frJson)->toHaveKey('Only JPG, PNG, and WebP images are accepted.');
        expect($frJson)->toHaveKey('Image must be under 2MB.');
        expect($frJson)->toHaveKey('Delete Image?');
        expect($frJson)->toHaveKey('Upload Images');
        expect($frJson)->toHaveKey('Primary');
        expect($frJson)->toHaveKey('Preview');
    });

    test('french translations are not empty', function () {
        $projectRoot = dirname(__DIR__, 3);
        $frJson = json_decode(file_get_contents($projectRoot . '/lang/fr.json'), true);

        expect($frJson['Add attractive photos to showcase your food and kitchen.'])->not->toBeEmpty();
        expect($frJson['Maximum :count images allowed.'])->not->toBeEmpty();
        expect($frJson['Delete Image?'])->not->toBeEmpty();
        expect($frJson['Upload Images'])->not->toBeEmpty();
    });
});
