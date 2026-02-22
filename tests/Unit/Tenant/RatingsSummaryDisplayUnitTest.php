<?php

/**
 * F-130: Ratings Summary Display — Unit Tests
 *
 * Tests TenantLandingService methods:
 * - getRatingsDisplayData: empty state, average, distribution, recent 5, showSeeAll
 * - getAllReviewsData: paginated reviews, distribution, pagination metadata
 * - BR-167: Average calculated with 1 decimal place
 * - BR-168: Star distribution per level (5 → 1)
 * - BR-169: Only 5 most recent reviews shown on landing page
 * - BR-172: "See all reviews" only when totalCount > 5
 * - BR-173: Empty state for zero reviews
 * - BR-175: Reviewer name anonymized (first name + last initial)
 * - RECENT_REVIEWS_COUNT constant = 5
 * - ALL_REVIEWS_PER_PAGE constant = 10
 */

use App\Models\Order;
use App\Models\Rating;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantLandingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    test()->seedRolesAndPermissions();
    Notification::fake();
    $this->service = app(TenantLandingService::class);
});

// ─── Constants ──────────────────────────────────────────────

test('RECENT_REVIEWS_COUNT constant is 5', function () {
    expect(TenantLandingService::RECENT_REVIEWS_COUNT)->toBe(5);
});

test('ALL_REVIEWS_PER_PAGE constant is 10', function () {
    expect(TenantLandingService::ALL_REVIEWS_PER_PAGE)->toBe(10);
});

// ─── getRatingsDisplayData — Empty State (BR-173) ─────────

test('BR-173: getRatingsDisplayData returns hasReviews=false for tenant with no ratings', function () {
    $tenant = Tenant::factory()->create();

    $result = $this->service->getRatingsDisplayData($tenant);

    expect($result['hasReviews'])->toBeFalse()
        ->and($result['totalCount'])->toBe(0)
        ->and($result['average'])->toBe(0.0)
        ->and($result['recentReviews'])->toBeEmpty()
        ->and($result['showSeeAll'])->toBeFalse()
        ->and($result['distribution'])->toBe([5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0]);
});

// ─── getRatingsDisplayData — Average (BR-167) ─────────────

test('BR-167: getRatingsDisplayData calculates correct average with 1 decimal', function () {
    $tenant = Tenant::factory()->create(['settings' => ['average_rating' => 4.3, 'total_ratings' => 3]]);

    $user = User::factory()->create();
    $order1 = Order::factory()->completed()->create(['tenant_id' => $tenant->id, 'client_id' => $user->id]);
    $order2 = Order::factory()->completed()->create(['tenant_id' => $tenant->id, 'client_id' => $user->id]);
    $order3 = Order::factory()->completed()->create(['tenant_id' => $tenant->id, 'client_id' => $user->id]);

    Rating::factory()->stars(5)->create(['order_id' => $order1->id, 'user_id' => $user->id, 'tenant_id' => $tenant->id]);
    Rating::factory()->stars(4)->create(['order_id' => $order2->id, 'user_id' => $user->id, 'tenant_id' => $tenant->id]);
    Rating::factory()->stars(4)->create(['order_id' => $order3->id, 'user_id' => $user->id, 'tenant_id' => $tenant->id]);

    $result = $this->service->getRatingsDisplayData($tenant);

    expect($result['hasReviews'])->toBeTrue()
        ->and($result['totalCount'])->toBe(3)
        ->and($result['average'])->toBe(4.3); // (5+4+4)/3 = 4.333 → 4.3
});

test('BR-167: single 5-star rating gives average 5.0', function () {
    $tenant = Tenant::factory()->create(['settings' => ['average_rating' => 5.0, 'total_ratings' => 1]]);
    $user = User::factory()->create();
    $order = Order::factory()->completed()->create(['tenant_id' => $tenant->id, 'client_id' => $user->id]);
    Rating::factory()->stars(5)->create(['order_id' => $order->id, 'user_id' => $user->id, 'tenant_id' => $tenant->id]);

    $result = $this->service->getRatingsDisplayData($tenant);

    expect($result['average'])->toBe(5.0)
        ->and($result['totalCount'])->toBe(1);
});

// ─── getRatingsDisplayData — Distribution (BR-168) ────────

test('BR-168: getRatingsDisplayData builds correct star distribution', function () {
    $tenant = Tenant::factory()->create(['settings' => ['average_rating' => 4.0, 'total_ratings' => 5]]);
    $user = User::factory()->create();

    $starsToCreate = [5, 5, 4, 3, 1];
    foreach ($starsToCreate as $stars) {
        $order = Order::factory()->completed()->create(['tenant_id' => $tenant->id, 'client_id' => $user->id]);
        Rating::factory()->stars($stars)->create(['order_id' => $order->id, 'user_id' => $user->id, 'tenant_id' => $tenant->id]);
    }

    $result = $this->service->getRatingsDisplayData($tenant);

    expect($result['distribution'])->toBe([5 => 2, 4 => 1, 3 => 1, 2 => 0, 1 => 1]);
});

test('BR-168: distribution keys are ordered 5 down to 1', function () {
    $tenant = Tenant::factory()->create(['settings' => ['average_rating' => 3.0, 'total_ratings' => 1]]);
    $user = User::factory()->create();
    $order = Order::factory()->completed()->create(['tenant_id' => $tenant->id, 'client_id' => $user->id]);
    Rating::factory()->stars(3)->create(['order_id' => $order->id, 'user_id' => $user->id, 'tenant_id' => $tenant->id]);

    $result = $this->service->getRatingsDisplayData($tenant);

    expect(array_keys($result['distribution']))->toBe([5, 4, 3, 2, 1]);
});

// ─── getRatingsDisplayData — Recent Reviews (BR-169) ──────

test('BR-169: getRatingsDisplayData returns only 5 most recent reviews', function () {
    $tenant = Tenant::factory()->create(['settings' => ['average_rating' => 4.0, 'total_ratings' => 7]]);
    $user = User::factory()->create();

    // Create 7 reviews at different times
    for ($i = 1; $i <= 7; $i++) {
        $order = Order::factory()->completed()->create(['tenant_id' => $tenant->id, 'client_id' => $user->id]);
        Rating::factory()->stars(4)->create([
            'order_id' => $order->id,
            'user_id' => $user->id,
            'tenant_id' => $tenant->id,
            'created_at' => now()->subDays(8 - $i),
            'updated_at' => now()->subDays(8 - $i),
        ]);
    }

    $result = $this->service->getRatingsDisplayData($tenant);

    expect($result['recentReviews'])->toHaveCount(5);
});

test('BR-169: recent reviews are sorted newest first', function () {
    $tenant = Tenant::factory()->create(['settings' => ['average_rating' => 4.0, 'total_ratings' => 3]]);
    $user = User::factory()->create();

    $dates = [now()->subDays(10), now()->subDays(5), now()->subDay()];
    $starsByDate = [1, 3, 5]; // oldest=1 star, newest=5 stars

    for ($i = 0; $i < 3; $i++) {
        $order = Order::factory()->completed()->create(['tenant_id' => $tenant->id, 'client_id' => $user->id]);
        Rating::factory()->stars($starsByDate[$i])->create([
            'order_id' => $order->id,
            'user_id' => $user->id,
            'tenant_id' => $tenant->id,
            'created_at' => $dates[$i],
            'updated_at' => $dates[$i],
        ]);
    }

    $result = $this->service->getRatingsDisplayData($tenant);

    expect($result['recentReviews'][0]['stars'])->toBe(5) // newest first
        ->and($result['recentReviews'][2]['stars'])->toBe(1); // oldest last
});

// ─── getRatingsDisplayData — See All (BR-172) ─────────────

test('BR-172: showSeeAll is false when exactly 5 reviews exist', function () {
    $tenant = Tenant::factory()->create(['settings' => ['average_rating' => 4.0, 'total_ratings' => 5]]);
    $user = User::factory()->create();

    for ($i = 0; $i < 5; $i++) {
        $order = Order::factory()->completed()->create(['tenant_id' => $tenant->id, 'client_id' => $user->id]);
        Rating::factory()->stars(4)->create(['order_id' => $order->id, 'user_id' => $user->id, 'tenant_id' => $tenant->id]);
    }

    $result = $this->service->getRatingsDisplayData($tenant);

    expect($result['showSeeAll'])->toBeFalse()
        ->and($result['totalCount'])->toBe(5);
});

test('BR-172: showSeeAll is true when more than 5 reviews exist', function () {
    $tenant = Tenant::factory()->create(['settings' => ['average_rating' => 4.0, 'total_ratings' => 6]]);
    $user = User::factory()->create();

    for ($i = 0; $i < 6; $i++) {
        $order = Order::factory()->completed()->create(['tenant_id' => $tenant->id, 'client_id' => $user->id]);
        Rating::factory()->stars(4)->create(['order_id' => $order->id, 'user_id' => $user->id, 'tenant_id' => $tenant->id]);
    }

    $result = $this->service->getRatingsDisplayData($tenant);

    expect($result['showSeeAll'])->toBeTrue()
        ->and($result['totalCount'])->toBe(6);
});

// ─── getRatingsDisplayData — Client Name (BR-175) ─────────

test('BR-175: reviewer name is anonymized to first name + last initial', function () {
    $tenant = Tenant::factory()->create(['settings' => ['average_rating' => 4.0, 'total_ratings' => 1]]);
    $user = User::factory()->create(['name' => 'Amara Ndongo']);
    $order = Order::factory()->completed()->create(['tenant_id' => $tenant->id, 'client_id' => $user->id]);
    Rating::factory()->withReview('Great food!')->stars(4)->create([
        'order_id' => $order->id,
        'user_id' => $user->id,
        'tenant_id' => $tenant->id,
    ]);

    $result = $this->service->getRatingsDisplayData($tenant);

    expect($result['recentReviews'][0]['clientName'])->toBe('Amara N.');
});

test('BR-175: reviewer with single name is shown as is', function () {
    $tenant = Tenant::factory()->create(['settings' => ['average_rating' => 5.0, 'total_ratings' => 1]]);
    $user = User::factory()->create(['name' => 'Latifa']);
    $order = Order::factory()->completed()->create(['tenant_id' => $tenant->id, 'client_id' => $user->id]);
    Rating::factory()->stars(5)->create([
        'order_id' => $order->id,
        'user_id' => $user->id,
        'tenant_id' => $tenant->id,
    ]);

    $result = $this->service->getRatingsDisplayData($tenant);

    expect($result['recentReviews'][0]['clientName'])->toBe('Latifa');
});

// ─── getAllReviewsData — Pagination ────────────────────────

test('getAllReviewsData returns empty state for tenant with no reviews', function () {
    $tenant = Tenant::factory()->create();

    $result = $this->service->getAllReviewsData($tenant, 1);

    expect($result['hasReviews'])->toBeFalse()
        ->and($result['reviews'])->toBeEmpty()
        ->and($result['pagination']['total'])->toBe(0)
        ->and($result['pagination']['hasMore'])->toBeFalse();
});

test('getAllReviewsData paginates with 10 reviews per page', function () {
    $tenant = Tenant::factory()->create(['settings' => ['average_rating' => 4.0, 'total_ratings' => 15]]);
    $user = User::factory()->create();

    for ($i = 0; $i < 15; $i++) {
        $order = Order::factory()->completed()->create(['tenant_id' => $tenant->id, 'client_id' => $user->id]);
        Rating::factory()->stars(4)->create(['order_id' => $order->id, 'user_id' => $user->id, 'tenant_id' => $tenant->id]);
    }

    $page1 = $this->service->getAllReviewsData($tenant, 1);
    $page2 = $this->service->getAllReviewsData($tenant, 2);

    expect($page1['reviews'])->toHaveCount(10)
        ->and($page1['pagination']['currentPage'])->toBe(1)
        ->and($page1['pagination']['lastPage'])->toBe(2)
        ->and($page1['pagination']['total'])->toBe(15)
        ->and($page1['pagination']['hasMore'])->toBeTrue()
        ->and($page2['reviews'])->toHaveCount(5)
        ->and($page2['pagination']['hasMore'])->toBeFalse();
});

test('getAllReviewsData returns reviews sorted newest first', function () {
    $tenant = Tenant::factory()->create(['settings' => ['average_rating' => 3.0, 'total_ratings' => 2]]);
    $user = User::factory()->create();

    $order1 = Order::factory()->completed()->create(['tenant_id' => $tenant->id, 'client_id' => $user->id]);
    Rating::factory()->stars(2)->create([
        'order_id' => $order1->id,
        'user_id' => $user->id,
        'tenant_id' => $tenant->id,
        'created_at' => now()->subDays(10),
        'updated_at' => now()->subDays(10),
    ]);

    $user2 = User::factory()->create();
    $order2 = Order::factory()->completed()->create(['tenant_id' => $tenant->id, 'client_id' => $user2->id]);
    Rating::factory()->stars(4)->create([
        'order_id' => $order2->id,
        'user_id' => $user2->id,
        'tenant_id' => $tenant->id,
        'created_at' => now()->subDay(),
        'updated_at' => now()->subDay(),
    ]);

    $result = $this->service->getAllReviewsData($tenant, 1);

    // Newest (4 stars) should be first
    expect($result['reviews'][0]['stars'])->toBe(4)
        ->and($result['reviews'][1]['stars'])->toBe(2);
});

test('getAllReviewsData includes distribution data', function () {
    $tenant = Tenant::factory()->create(['settings' => ['average_rating' => 4.5, 'total_ratings' => 4]]);
    $user = User::factory()->create();

    $starsToCreate = [5, 5, 4, 4];
    foreach ($starsToCreate as $stars) {
        $order = Order::factory()->completed()->create(['tenant_id' => $tenant->id, 'client_id' => $user->id]);
        Rating::factory()->stars($stars)->create(['order_id' => $order->id, 'user_id' => $user->id, 'tenant_id' => $tenant->id]);
    }

    $result = $this->service->getAllReviewsData($tenant, 1);

    expect($result['distribution'])->toBe([5 => 2, 4 => 2, 3 => 0, 2 => 0, 1 => 0]);
});

// ─── Data structure integrity ──────────────────────────────

test('getRatingsDisplayData review entries have all required keys', function () {
    $tenant = Tenant::factory()->create(['settings' => ['average_rating' => 5.0, 'total_ratings' => 1]]);
    $user = User::factory()->create(['name' => 'Marie Fontem']);
    $order = Order::factory()->completed()->create(['tenant_id' => $tenant->id, 'client_id' => $user->id]);
    Rating::factory()->withReview('Delicious!')->stars(5)->create([
        'order_id' => $order->id,
        'user_id' => $user->id,
        'tenant_id' => $tenant->id,
    ]);

    $result = $this->service->getRatingsDisplayData($tenant);

    expect($result['recentReviews'][0])
        ->toHaveKeys(['id', 'stars', 'review', 'date', 'relativeDate', 'clientName'])
        ->and($result['recentReviews'][0]['stars'])->toBe(5)
        ->and($result['recentReviews'][0]['review'])->toBe('Delicious!')
        ->and($result['recentReviews'][0]['clientName'])->toBe('Marie F.');
});

test('getRatingsDisplayData handles ratings with no review text', function () {
    $tenant = Tenant::factory()->create(['settings' => ['average_rating' => 4.0, 'total_ratings' => 1]]);
    $user = User::factory()->create();
    $order = Order::factory()->completed()->create(['tenant_id' => $tenant->id, 'client_id' => $user->id]);
    Rating::factory()->create([
        'order_id' => $order->id,
        'user_id' => $user->id,
        'tenant_id' => $tenant->id,
        'stars' => 4,
        'review' => null,
    ]);

    $result = $this->service->getRatingsDisplayData($tenant);

    expect($result['recentReviews'][0]['review'])->toBeNull();
});

test('getRatingsDisplayData is tenant-scoped', function () {
    $tenant1 = Tenant::factory()->create(['settings' => ['average_rating' => 5.0, 'total_ratings' => 1]]);
    $tenant2 = Tenant::factory()->create();
    $user = User::factory()->create();

    // Add a rating for tenant1 only
    $order = Order::factory()->completed()->create(['tenant_id' => $tenant1->id, 'client_id' => $user->id]);
    Rating::factory()->stars(5)->create(['order_id' => $order->id, 'user_id' => $user->id, 'tenant_id' => $tenant1->id]);

    $result1 = $this->service->getRatingsDisplayData($tenant1);
    $result2 = $this->service->getRatingsDisplayData($tenant2);

    expect($result1['hasReviews'])->toBeTrue()
        ->and($result1['totalCount'])->toBe(1)
        ->and($result2['hasReviews'])->toBeFalse()
        ->and($result2['totalCount'])->toBe(0);
});
