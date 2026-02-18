<?php

/**
 * F-109: Meal Image Upload & Carousel — Unit Tests
 *
 * Tests for MealImage model, MealImageService, MealImageController,
 * and related business rules.
 */

use App\Models\Meal;
use App\Models\MealImage;
use App\Services\MealImageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    test()->seedRolesAndPermissions();
    Storage::fake('public');
});

// ===================================================================
// MealImage Model Tests
// ===================================================================

it('has correct table name', function () {
    $image = new MealImage;
    expect($image->getTable())->toBe('meal_images');
});

it('has correct fillable attributes', function () {
    $image = new MealImage;
    expect($image->getFillable())->toBe([
        'meal_id',
        'path',
        'thumbnail_path',
        'position',
        'original_filename',
        'mime_type',
        'file_size',
    ]);
});

it('casts position and file_size correctly', function () {
    $image = MealImage::factory()->create([
        'position' => '2',
        'file_size' => '1048576',
    ]);
    expect($image->position)->toBeInt()
        ->and($image->file_size)->toBeInt();
});

it('belongs to a meal', function () {
    $meal = Meal::factory()->create();
    $image = MealImage::factory()->create(['meal_id' => $meal->id]);

    expect($image->meal)->toBeInstanceOf(Meal::class)
        ->and($image->meal->id)->toBe($meal->id);
});

it('generates url attribute from path', function () {
    $image = MealImage::factory()->create([
        'path' => 'meal-images/test.jpg',
    ]);

    expect($image->url)->toContain('storage/meal-images/test.jpg');
});

it('generates thumbnail url attribute', function () {
    $image = MealImage::factory()->create([
        'thumbnail_path' => 'meal-images/thumbs/test.jpg',
    ]);

    expect($image->thumbnail_url)->toContain('storage/meal-images/thumbs/test.jpg');
});

it('falls back to main url when no thumbnail', function () {
    $image = MealImage::factory()->create([
        'path' => 'meal-images/test.jpg',
        'thumbnail_path' => null,
    ]);

    expect($image->thumbnail_url)->toBe($image->url);
});

it('formats file size correctly', function () {
    // Bytes
    $image = MealImage::factory()->create(['file_size' => 500]);
    expect($image->formatted_size)->toBe('500 B');

    // KB
    $image2 = MealImage::factory()->create(['file_size' => 512000]);
    expect($image2->formatted_size)->toBe('500 KB');

    // MB
    $image3 = MealImage::factory()->create(['file_size' => 1572864]);
    expect($image3->formatted_size)->toBe('1.5 MB');
});

it('orders by position with ordered scope', function () {
    $meal = Meal::factory()->create();
    MealImage::factory()->create(['meal_id' => $meal->id, 'position' => 2]);
    MealImage::factory()->create(['meal_id' => $meal->id, 'position' => 0]);
    MealImage::factory()->create(['meal_id' => $meal->id, 'position' => 1]);

    $images = MealImage::query()->where('meal_id', $meal->id)->ordered()->get();
    expect($images->pluck('position')->toArray())->toBe([0, 1, 2]);
});

it('defines max images constant as 3', function () {
    expect(MealImage::MAX_IMAGES)->toBe(3);
});

it('defines max file size as 2048 KB', function () {
    expect(MealImage::MAX_FILE_SIZE_KB)->toBe(2048);
});

it('defines accepted mime types', function () {
    expect(MealImage::ACCEPTED_MIMES)->toBe([
        'image/jpeg',
        'image/png',
        'image/webp',
    ]);
});

// ===================================================================
// Meal Model Relationship Tests
// ===================================================================

it('meal has many images ordered by position', function () {
    $meal = Meal::factory()->create();
    MealImage::factory()->create(['meal_id' => $meal->id, 'position' => 1]);
    MealImage::factory()->create(['meal_id' => $meal->id, 'position' => 0]);

    $images = $meal->images;
    expect($images)->toHaveCount(2)
        ->and($images->first()->position)->toBe(0);
});

it('meal images are deleted when meal is force deleted', function () {
    $meal = Meal::factory()->create();
    MealImage::factory()->count(2)->create(['meal_id' => $meal->id]);

    expect(MealImage::where('meal_id', $meal->id)->count())->toBe(2);

    $meal->forceDelete();
    expect(MealImage::where('meal_id', $meal->id)->count())->toBe(0);
});

// ===================================================================
// MealImageService Tests
// ===================================================================

it('can check if meal can upload more images', function () {
    $service = app(MealImageService::class);
    $meal = Meal::factory()->create();

    expect($service->canUploadMore($meal))->toBeTrue();

    MealImage::factory()->count(3)->create(['meal_id' => $meal->id]);
    expect($service->canUploadMore($meal))->toBeFalse();
});

it('returns remaining upload slots', function () {
    $service = app(MealImageService::class);
    $meal = Meal::factory()->create();

    expect($service->getRemainingSlots($meal))->toBe(3);

    MealImage::factory()->create(['meal_id' => $meal->id]);
    expect($service->getRemainingSlots($meal))->toBe(2);

    MealImage::factory()->count(2)->create(['meal_id' => $meal->id]);
    expect($service->getRemainingSlots($meal))->toBe(0);
});

it('uploads an image successfully', function () {
    $service = app(MealImageService::class);
    $meal = Meal::factory()->create();

    $file = UploadedFile::fake()->image('meal-photo.jpg', 800, 600)->size(1500);

    $result = $service->uploadImage($meal, $file);

    expect($result['success'])->toBeTrue()
        ->and($result['image'])->toBeInstanceOf(MealImage::class)
        ->and($result['image']->meal_id)->toBe($meal->id)
        ->and($result['image']->position)->toBe(0)
        ->and($result['image']->original_filename)->toBe('meal-photo.jpg')
        ->and($result['image']->mime_type)->toBe('image/jpeg');

    // Check files were stored
    Storage::disk('public')->assertExists($result['image']->path);
    Storage::disk('public')->assertExists($result['image']->thumbnail_path);
});

it('assigns sequential positions to uploaded images', function () {
    $service = app(MealImageService::class);
    $meal = Meal::factory()->create();

    $file1 = UploadedFile::fake()->image('first.jpg', 800, 600)->size(1000);
    $file2 = UploadedFile::fake()->image('second.png', 800, 600)->size(1000);

    $result1 = $service->uploadImage($meal, $file1);
    $result2 = $service->uploadImage($meal, $file2);

    expect($result1['image']->position)->toBe(0)
        ->and($result2['image']->position)->toBe(1);
});

it('rejects upload when max images reached', function () {
    $service = app(MealImageService::class);
    $meal = Meal::factory()->create();

    MealImage::factory()->count(3)->create(['meal_id' => $meal->id]);

    $file = UploadedFile::fake()->image('extra.jpg', 800, 600);
    $result = $service->uploadImage($meal, $file);

    expect($result['success'])->toBeFalse()
        ->and($result['error'])->toContain('3');
});

it('reorders images correctly', function () {
    $service = app(MealImageService::class);
    $meal = Meal::factory()->create();

    $img1 = MealImage::factory()->create(['meal_id' => $meal->id, 'position' => 0]);
    $img2 = MealImage::factory()->create(['meal_id' => $meal->id, 'position' => 1]);
    $img3 = MealImage::factory()->create(['meal_id' => $meal->id, 'position' => 2]);

    // Reorder: 3, 1, 2
    $success = $service->reorderImages($meal, [$img3->id, $img1->id, $img2->id]);

    expect($success)->toBeTrue();

    $img1->refresh();
    $img2->refresh();
    $img3->refresh();

    expect($img3->position)->toBe(0)
        ->and($img1->position)->toBe(1)
        ->and($img2->position)->toBe(2);
});

it('rejects reorder with invalid image ids', function () {
    $service = app(MealImageService::class);
    $meal = Meal::factory()->create();
    $otherMeal = Meal::factory()->create();

    $img1 = MealImage::factory()->create(['meal_id' => $meal->id]);
    $imgOther = MealImage::factory()->create(['meal_id' => $otherMeal->id]);

    $success = $service->reorderImages($meal, [$img1->id, $imgOther->id]);
    expect($success)->toBeFalse();
});

it('deletes an image and cleans up files', function () {
    $service = app(MealImageService::class);
    $meal = Meal::factory()->create();

    $file = UploadedFile::fake()->image('delete-me.jpg', 800, 600)->size(500);
    $uploadResult = $service->uploadImage($meal, $file);
    $image = $uploadResult['image'];

    $path = $image->path;
    $thumbPath = $image->thumbnail_path;

    $result = $service->deleteImage($meal, $image->id);

    expect($result['success'])->toBeTrue();

    // Check record deleted
    expect(MealImage::find($image->id))->toBeNull();

    // Check files cleaned up
    Storage::disk('public')->assertMissing($path);
    Storage::disk('public')->assertMissing($thumbPath);
});

it('normalizes positions after deletion', function () {
    $service = app(MealImageService::class);
    $meal = Meal::factory()->create();

    $img1 = MealImage::factory()->create(['meal_id' => $meal->id, 'position' => 0]);
    $img2 = MealImage::factory()->create(['meal_id' => $meal->id, 'position' => 1]);
    $img3 = MealImage::factory()->create(['meal_id' => $meal->id, 'position' => 2]);

    $service->deleteImage($meal, $img2->id);

    $img1->refresh();
    $img3->refresh();

    expect($img1->position)->toBe(0)
        ->and($img3->position)->toBe(1);
});

it('returns error when deleting nonexistent image', function () {
    $service = app(MealImageService::class);
    $meal = Meal::factory()->create();

    $result = $service->deleteImage($meal, 99999);
    expect($result['success'])->toBeFalse()
        ->and($result['error'])->not->toBeEmpty();
});

it('formats images data for frontend', function () {
    $service = app(MealImageService::class);
    $meal = Meal::factory()->create();

    MealImage::factory()->create([
        'meal_id' => $meal->id,
        'position' => 0,
        'original_filename' => 'my-meal.jpg',
        'file_size' => 1048576,
    ]);

    $data = $service->getImagesData($meal);

    expect($data)->toHaveCount(1)
        ->and($data[0])->toHaveKeys(['id', 'url', 'thumbnail', 'name', 'size', 'formattedSize', 'position'])
        ->and($data[0]['name'])->toBe('my-meal.jpg')
        ->and($data[0]['formattedSize'])->toBe('1 MB')
        ->and($data[0]['position'])->toBe(0);
});

// ===================================================================
// Controller Permission Tests
// ===================================================================

it('requires can-manage-meals permission for upload', function () {
    $data = test()->createTenantWithCook();
    $tenant = $data['tenant'];
    $meal = Meal::factory()->create(['tenant_id' => $tenant->id]);
    $client = test()->createUserWithRole('client');

    $url = 'https://'.$tenant->slug.'.'.parse_url(config('app.url'), PHP_URL_HOST).'/dashboard/meals/'.$meal->id.'/images/upload';

    $this->actingAs($client)
        ->post($url, ['images' => [UploadedFile::fake()->image('test.jpg')]])
        ->assertStatus(403);
});

it('requires can-manage-meals permission for reorder', function () {
    $data = test()->createTenantWithCook();
    $tenant = $data['tenant'];
    $meal = Meal::factory()->create(['tenant_id' => $tenant->id]);
    $client = test()->createUserWithRole('client');

    $url = 'https://'.$tenant->slug.'.'.parse_url(config('app.url'), PHP_URL_HOST).'/dashboard/meals/'.$meal->id.'/images/reorder';

    $this->actingAs($client)
        ->post($url, ['orderedIds' => [1]])
        ->assertStatus(403);
});

it('requires can-manage-meals permission for delete', function () {
    $data = test()->createTenantWithCook();
    $tenant = $data['tenant'];
    $meal = Meal::factory()->create(['tenant_id' => $tenant->id]);
    $client = test()->createUserWithRole('client');

    $url = 'https://'.$tenant->slug.'.'.parse_url(config('app.url'), PHP_URL_HOST).'/dashboard/meals/'.$meal->id.'/images/1';

    $this->actingAs($client)
        ->delete($url)
        ->assertStatus(403);
});

it('cook can upload images', function () {
    $data = test()->createTenantWithCook();
    $tenant = $data['tenant'];
    $cook = $data['cook'];
    $tenant->update(['cook_id' => $cook->id]);
    $meal = Meal::factory()->create(['tenant_id' => $tenant->id]);

    $url = 'https://'.$tenant->slug.'.'.parse_url(config('app.url'), PHP_URL_HOST).'/dashboard/meals/'.$meal->id.'/images/upload';

    $this->actingAs($cook)
        ->post($url, ['images' => [UploadedFile::fake()->image('test.jpg', 800, 600)->size(500)]])
        ->assertRedirect();

    expect(MealImage::where('meal_id', $meal->id)->count())->toBe(1);
});

it('validates image file format', function () {
    $data = test()->createTenantWithCook();
    $tenant = $data['tenant'];
    $cook = $data['cook'];
    $tenant->update(['cook_id' => $cook->id]);
    $meal = Meal::factory()->create(['tenant_id' => $tenant->id]);

    $url = 'https://'.$tenant->slug.'.'.parse_url(config('app.url'), PHP_URL_HOST).'/dashboard/meals/'.$meal->id.'/images/upload';

    $this->actingAs($cook)
        ->post($url, ['images' => [UploadedFile::fake()->create('test.gif', 500, 'image/gif')]])
        ->assertSessionHasErrors('images.0');
});

it('validates image file size', function () {
    $data = test()->createTenantWithCook();
    $tenant = $data['tenant'];
    $cook = $data['cook'];
    $tenant->update(['cook_id' => $cook->id]);
    $meal = Meal::factory()->create(['tenant_id' => $tenant->id]);

    $url = 'https://'.$tenant->slug.'.'.parse_url(config('app.url'), PHP_URL_HOST).'/dashboard/meals/'.$meal->id.'/images/upload';

    $this->actingAs($cook)
        ->post($url, ['images' => [UploadedFile::fake()->image('large.jpg')->size(3000)]])
        ->assertSessionHasErrors('images.0');
});

it('blocks upload beyond max images', function () {
    $data = test()->createTenantWithCook();
    $tenant = $data['tenant'];
    $cook = $data['cook'];
    $tenant->update(['cook_id' => $cook->id]);
    $meal = Meal::factory()->create(['tenant_id' => $tenant->id]);

    MealImage::factory()->count(3)->create(['meal_id' => $meal->id]);

    $url = 'https://'.$tenant->slug.'.'.parse_url(config('app.url'), PHP_URL_HOST).'/dashboard/meals/'.$meal->id.'/images/upload';

    $this->actingAs($cook)
        ->post($url, ['images' => [UploadedFile::fake()->image('extra.jpg', 800, 600)->size(500)]]);

    expect(MealImage::where('meal_id', $meal->id)->count())->toBe(3);
});

it('cook can delete their meal image', function () {
    $data = test()->createTenantWithCook();
    $tenant = $data['tenant'];
    $cook = $data['cook'];
    $tenant->update(['cook_id' => $cook->id]);
    $meal = Meal::factory()->create(['tenant_id' => $tenant->id]);
    $image = MealImage::factory()->create(['meal_id' => $meal->id]);

    $url = 'https://'.$tenant->slug.'.'.parse_url(config('app.url'), PHP_URL_HOST).'/dashboard/meals/'.$meal->id.'/images/'.$image->id;

    $this->actingAs($cook)
        ->delete($url)
        ->assertRedirect();

    expect(MealImage::find($image->id))->toBeNull();
});

it('scopes meal to tenant on upload', function () {
    $data1 = test()->createTenantWithCook();
    $tenant1 = $data1['tenant'];
    $cook1 = $data1['cook'];
    $tenant1->update(['cook_id' => $cook1->id]);

    $data2 = test()->createTenantWithCook();
    $tenant2 = $data2['tenant'];
    $meal = Meal::factory()->create(['tenant_id' => $tenant2->id]);

    $url = 'https://'.$tenant1->slug.'.'.parse_url(config('app.url'), PHP_URL_HOST).'/dashboard/meals/'.$meal->id.'/images/upload';

    $this->actingAs($cook1)
        ->post($url, ['images' => [UploadedFile::fake()->image('test.jpg', 800, 600)->size(500)]])
        ->assertStatus(404);
});

// ===================================================================
// MealImageFactory Tests
// ===================================================================

it('creates meal image with factory', function () {
    $image = MealImage::factory()->create();

    expect($image)->toBeInstanceOf(MealImage::class)
        ->and($image->path)->not->toBeEmpty()
        ->and($image->original_filename)->not->toBeEmpty()
        ->and($image->mime_type)->toBeIn(MealImage::ACCEPTED_MIMES);
});

it('factory positioned state works', function () {
    $image = MealImage::factory()->positioned(2)->create();
    expect($image->position)->toBe(2);
});

it('factory jpeg state works', function () {
    $image = MealImage::factory()->jpeg()->create();
    expect($image->mime_type)->toBe('image/jpeg');
});

it('factory png state works', function () {
    $image = MealImage::factory()->png()->create();
    expect($image->mime_type)->toBe('image/png');
});

it('factory webp state works', function () {
    $image = MealImage::factory()->webp()->create();
    expect($image->mime_type)->toBe('image/webp');
});
