<?php

/**
 * F-127: Cook Brand Header Section — Unit Tests
 *
 * Tests for TenantLandingService cook profile data building,
 * bio truncation, rating badge, and hero section rendering.
 */

use App\Models\Tenant;
use App\Services\TenantLandingService;
use App\Services\TenantThemeService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->tenantThemeService = app(TenantThemeService::class);
    $this->landingService = new TenantLandingService($this->tenantThemeService);
});

// ============================================
// BR-136: Brand name from locale
// ============================================

it('includes cook brand name in profile data', function () {
    $tenant = Tenant::factory()->create([
        'name_en' => 'Chef Latifa',
        'name_fr' => 'Chef Latifa Cuisine',
    ]);

    $data = $this->landingService->getLandingPageData($tenant);

    expect($data['cookProfile']['name'])->toBeString()->not->toBeEmpty();
});

// ============================================
// BR-137: Tagline = bio excerpt (150 chars)
// ============================================

it('truncates bio to 150 characters for excerpt', function () {
    $longBio = str_repeat('Authentic Cameroonian cuisine made with love. ', 10);
    $tenant = Tenant::factory()->create([
        'description_en' => $longBio,
        'description_fr' => $longBio,
    ]);

    $data = $this->landingService->getLandingPageData($tenant);

    expect($data['cookProfile']['bioExcerpt'])
        ->not->toBeNull()
        ->and(mb_strlen($data['cookProfile']['bioExcerpt']))->toBeLessThanOrEqual(154); // 150 + '...'
    expect($data['cookProfile']['bioExcerpt'])->toEndWith('...');
});

it('returns full bio when shorter than 150 characters', function () {
    $shortBio = 'Short and sweet Cameroonian cuisine.';
    $tenant = Tenant::factory()->create([
        'description_en' => $shortBio,
        'description_fr' => $shortBio,
    ]);

    $data = $this->landingService->getLandingPageData($tenant);

    expect($data['cookProfile']['bioExcerpt'])->toBe($shortBio);
    expect($data['cookProfile']['bioExcerpt'])->not->toEndWith('...');
});

it('returns null bio excerpt when bio is empty', function () {
    $tenant = Tenant::factory()->create([
        'description_en' => null,
        'description_fr' => null,
    ]);

    $data = $this->landingService->getLandingPageData($tenant);

    expect($data['cookProfile']['bioExcerpt'])->toBeNull();
});

it('returns full bio separately from excerpt', function () {
    $longBio = trim(str_repeat('Delicious home cooking. ', 20));
    $tenant = Tenant::factory()->create([
        'description_en' => $longBio,
        'description_fr' => $longBio,
    ]);

    $data = $this->landingService->getLandingPageData($tenant);

    // Full bio is untruncated
    expect($data['cookProfile']['bio'])->toBe($longBio);
    // Excerpt is truncated
    expect($data['cookProfile']['bioExcerpt'])->not->toBe($longBio);
});

// ============================================
// BR-141/142: Rating badge data
// ============================================

it('includes rating data in cook profile', function () {
    $tenant = Tenant::factory()->create();

    $data = $this->landingService->getLandingPageData($tenant);

    expect($data['cookProfile']['rating'])->toBeArray()
        ->toHaveKeys(['average', 'count', 'hasReviews']);
    expect($data['cookProfile']['rating']['average'])->toBeFloat();
    expect($data['cookProfile']['rating']['count'])->toBeInt();
    expect($data['cookProfile']['rating']['hasReviews'])->toBeBool();
});

// ============================================
// BR-143: New Cook badge for zero reviews
// ============================================

it('shows no reviews for new cook (forward-compatible)', function () {
    $tenant = Tenant::factory()->create();

    $data = $this->landingService->getLandingPageData($tenant);

    // Until F-176 rating system is built, all cooks are "New Cook"
    expect($data['cookProfile']['rating']['hasReviews'])->toBeFalse();
    expect($data['cookProfile']['rating']['average'])->toBe(0.0);
    expect($data['cookProfile']['rating']['count'])->toBe(0);
});

// ============================================
// Cover image data
// ============================================

it('includes cover image data in profile', function () {
    $tenant = Tenant::factory()->create();

    $data = $this->landingService->getLandingPageData($tenant);

    expect($data['cookProfile'])->toHaveKeys(['hasImages', 'coverImages']);
    expect($data['cookProfile']['hasImages'])->toBeBool();
    expect($data['cookProfile']['coverImages'])->toBeArray();
});

it('sets hasImages to false when no cover images', function () {
    $tenant = Tenant::factory()->create();

    $data = $this->landingService->getLandingPageData($tenant);

    expect($data['cookProfile']['hasImages'])->toBeFalse();
    expect($data['cookProfile']['coverImages'])->toBeEmpty();
});

// ============================================
// Section data
// ============================================

it('returns all required landing page sections', function () {
    $tenant = Tenant::factory()->create();

    $data = $this->landingService->getLandingPageData($tenant);

    expect($data['sections'])->toHaveKeys([
        'hero', 'meals', 'about', 'ratings', 'testimonials', 'schedule', 'delivery',
    ]);
});

it('returns tenant and theme config in landing data', function () {
    $tenant = Tenant::factory()->create();

    $data = $this->landingService->getLandingPageData($tenant);

    expect($data)->toHaveKeys(['tenant', 'themeConfig', 'cookProfile', 'sections']);
    expect($data['tenant'])->toBeInstanceOf(Tenant::class);
});

// ============================================
// Social links and contact data
// ============================================

it('includes social links in profile data', function () {
    $tenant = Tenant::factory()->withBrandInfo()->create();

    $data = $this->landingService->getLandingPageData($tenant);

    expect($data['cookProfile']['socialLinks'])->toHaveKeys(['facebook', 'instagram', 'tiktok']);
    expect($data['cookProfile']['whatsapp'])->not->toBeNull();
    expect($data['cookProfile']['phone'])->not->toBeNull();
});

it('handles tenant with no contact info', function () {
    $tenant = Tenant::factory()->create([
        'whatsapp' => null,
        'phone' => null,
        'social_facebook' => null,
        'social_instagram' => null,
        'social_tiktok' => null,
    ]);

    $data = $this->landingService->getLandingPageData($tenant);

    expect($data['cookProfile']['whatsapp'])->toBeNull();
    expect($data['cookProfile']['phone'])->toBeNull();
    expect($data['cookProfile']['socialLinks']['facebook'])->toBeNull();
});

// ============================================
// Bio truncation edge cases
// ============================================

it('truncates at word boundary when possible', function () {
    // Create a bio that is longer than 150 chars with a word boundary before 150
    $bio = 'This is a really nice bio about Cameroonian cooking that talks about local ingredients and traditional recipes passed down through many many many many many generations of talented cooks';
    $tenant = Tenant::factory()->create([
        'description_en' => $bio,
        'description_fr' => $bio,
    ]);

    $data = $this->landingService->getLandingPageData($tenant);
    $excerpt = $data['cookProfile']['bioExcerpt'];

    // Should end with ...
    expect($excerpt)->toEndWith('...');
    // Total length should be at or under 154 (150 + '...')
    expect(mb_strlen($excerpt))->toBeLessThanOrEqual(154);
    // The text before ... should be a substring of the original bio
    $withoutEllipsis = mb_substr($excerpt, 0, -3);
    expect(str_starts_with($bio, $withoutEllipsis))->toBeTrue();
});

it('handles bio that is exactly 150 characters', function () {
    $bio = str_pad('Exactly right', 150, ' padding text to make it exactly one hundred and fifty characters in total for the bio.');
    $bio = mb_substr($bio, 0, 150);
    $tenant = Tenant::factory()->create([
        'description_en' => $bio,
        'description_fr' => $bio,
    ]);

    $data = $this->landingService->getLandingPageData($tenant);

    expect($data['cookProfile']['bioExcerpt'])->toBe($bio);
    expect($data['cookProfile']['bioExcerpt'])->not->toEndWith('...');
});
