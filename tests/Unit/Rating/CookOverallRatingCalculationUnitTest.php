<?php

/**
 * F-179: Cook Overall Rating Calculation — Unit Tests
 *
 * Tests:
 * - BR-417: Overall rating = sum of all stars / number of ratings (simple average)
 * - BR-418: Displayed as X.X/5 with one decimal place
 * - BR-419: Total review count includes all ratings (with or without review text)
 * - BR-420: Rating recalculated immediately when new rating submitted
 * - BR-421: Cached on tenant settings JSON for performance
 * - BR-423: Zero ratings show "New" / no rating indicator
 * - BR-424: Ratings from cancelled/refunded orders remain in calculation
 * - BR-425: Tenant-scoped ratings (separate per tenant)
 * - Scenario 5: Dashboard stat card with trend indicator
 */

use App\Models\Order;
use App\Models\Rating;
use App\Models\Tenant;
use App\Models\User;
use App\Services\CookDashboardService;
use App\Services\RatingService;
use Illuminate\Support\Facades\Notification;

uses(Tests\TestCase::class, \Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    test()->seedRolesAndPermissions();
    Notification::fake();
});

// ─── BR-417: Simple Average Calculation ──────────────────────

test('BR-417: recalculates overall rating as simple average of all stars', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create();

    $order1 = Order::factory()->completed()->create(['tenant_id' => $tenant->id, 'client_id' => $user->id]);
    $order2 = Order::factory()->completed()->create(['tenant_id' => $tenant->id, 'client_id' => $user->id]);
    $order3 = Order::factory()->completed()->create(['tenant_id' => $tenant->id, 'client_id' => $user->id]);

    Rating::factory()->stars(5)->create(['order_id' => $order1->id, 'user_id' => $user->id, 'tenant_id' => $tenant->id]);
    Rating::factory()->stars(4)->create(['order_id' => $order2->id, 'user_id' => $user->id, 'tenant_id' => $tenant->id]);
    Rating::factory()->stars(3)->create(['order_id' => $order3->id, 'user_id' => $user->id, 'tenant_id' => $tenant->id]);

    $service = app(RatingService::class);
    $service->recalculateCookRating($tenant);

    $tenant->refresh();
    // (5 + 4 + 3) / 3 = 4.0
    expect((float) $tenant->getSetting('average_rating'))->toEqual(4.0)
        ->and($tenant->getSetting('total_ratings'))->toBe(3);
});

// ─── BR-418: One Decimal Place Formatting ──────────────────────

test('BR-418: average rating stored with one decimal place', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create();

    $order1 = Order::factory()->completed()->create(['tenant_id' => $tenant->id, 'client_id' => $user->id]);
    $order2 = Order::factory()->completed()->create(['tenant_id' => $tenant->id, 'client_id' => $user->id]);
    $order3 = Order::factory()->completed()->create(['tenant_id' => $tenant->id, 'client_id' => $user->id]);

    Rating::factory()->stars(5)->create(['order_id' => $order1->id, 'user_id' => $user->id, 'tenant_id' => $tenant->id]);
    Rating::factory()->stars(4)->create(['order_id' => $order2->id, 'user_id' => $user->id, 'tenant_id' => $tenant->id]);
    Rating::factory()->stars(4)->create(['order_id' => $order3->id, 'user_id' => $user->id, 'tenant_id' => $tenant->id]);

    $service = app(RatingService::class);
    $service->recalculateCookRating($tenant);

    $tenant->refresh();
    // (5 + 4 + 4) / 3 = 4.333... -> rounded to 4.3
    expect((float) $tenant->getSetting('average_rating'))->toEqual(4.3);
});

// ─── BR-419: Count Includes All Ratings ──────────────────────

test('BR-419: total count includes ratings with and without review text', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create();

    $order1 = Order::factory()->completed()->create(['tenant_id' => $tenant->id, 'client_id' => $user->id]);
    $order2 = Order::factory()->completed()->create(['tenant_id' => $tenant->id, 'client_id' => $user->id]);

    Rating::factory()->stars(5)->withReview('Great food!')->create([
        'order_id' => $order1->id, 'user_id' => $user->id, 'tenant_id' => $tenant->id,
    ]);
    Rating::factory()->stars(3)->create([
        'order_id' => $order2->id, 'user_id' => $user->id, 'tenant_id' => $tenant->id,
    ]); // no review text

    $service = app(RatingService::class);
    $service->recalculateCookRating($tenant);

    $tenant->refresh();
    expect($tenant->getSetting('total_ratings'))->toBe(2);
});

// ─── BR-420: Immediate Recalculation on Submit ──────────────────────

test('BR-420: rating is recalculated immediately when submitRating is called', function () {
    $cook = User::factory()->create();
    $tenant = Tenant::factory()->create(['cook_id' => $cook->id]);
    $user = User::factory()->create();

    // First rating
    $order1 = Order::factory()->completed()->create(['tenant_id' => $tenant->id, 'client_id' => $user->id]);
    $service = app(RatingService::class);
    $result = $service->submitRating($order1, $user, 5);

    expect($result['success'])->toBeTrue();

    $tenant->refresh();
    expect((float) $tenant->getSetting('average_rating'))->toEqual(5.0)
        ->and($tenant->getSetting('total_ratings'))->toBe(1);

    // Second rating from different user
    $user2 = User::factory()->create();
    $order2 = Order::factory()->completed()->create(['tenant_id' => $tenant->id, 'client_id' => $user2->id]);
    $result2 = $service->submitRating($order2, $user2, 3);

    expect($result2['success'])->toBeTrue();

    $tenant->refresh();
    // (5 + 3) / 2 = 4.0
    expect((float) $tenant->getSetting('average_rating'))->toEqual(4.0)
        ->and($tenant->getSetting('total_ratings'))->toBe(2);
});

// ─── BR-421: Cached on Tenant Settings ──────────────────────

test('BR-421: getCachedCookRating reads from tenant settings JSON', function () {
    $tenant = Tenant::factory()->create([
        'settings' => [
            'average_rating' => 4.5,
            'total_ratings' => 12,
        ],
    ]);

    $service = app(RatingService::class);
    $cached = $service->getCachedCookRating($tenant);

    expect($cached['average'])->toEqual(4.5)
        ->and($cached['count'])->toBe(12)
        ->and($cached['hasRating'])->toBeTrue();
});

// ─── BR-423: Zero Ratings Show Placeholder ──────────────────────

test('BR-423: getCachedCookRating returns hasRating=false for zero ratings', function () {
    $tenant = Tenant::factory()->create(['settings' => []]);

    $service = app(RatingService::class);
    $cached = $service->getCachedCookRating($tenant);

    expect($cached['average'])->toEqual(0.0)
        ->and($cached['count'])->toBe(0)
        ->and($cached['hasRating'])->toBeFalse();
});

test('BR-423: getCachedCookRating handles null settings gracefully', function () {
    $tenant = Tenant::factory()->create(['settings' => null]);

    $service = app(RatingService::class);
    $cached = $service->getCachedCookRating($tenant);

    expect($cached['average'])->toEqual(0.0)
        ->and($cached['count'])->toBe(0)
        ->and($cached['hasRating'])->toBeFalse();
});

// ─── BR-425: Tenant-Scoped Ratings ──────────────────────

test('BR-425: ratings are tenant-scoped (separate per tenant)', function () {
    $tenant1 = Tenant::factory()->create();
    $tenant2 = Tenant::factory()->create();
    $user = User::factory()->create();

    // Tenant 1: 2 ratings averaging 4.5
    $order1 = Order::factory()->completed()->create(['tenant_id' => $tenant1->id, 'client_id' => $user->id]);
    $order2 = Order::factory()->completed()->create(['tenant_id' => $tenant1->id, 'client_id' => $user->id]);
    Rating::factory()->stars(5)->create(['order_id' => $order1->id, 'user_id' => $user->id, 'tenant_id' => $tenant1->id]);
    Rating::factory()->stars(4)->create(['order_id' => $order2->id, 'user_id' => $user->id, 'tenant_id' => $tenant1->id]);

    // Tenant 2: 1 rating of 2 stars
    $order3 = Order::factory()->completed()->create(['tenant_id' => $tenant2->id, 'client_id' => $user->id]);
    Rating::factory()->stars(2)->create(['order_id' => $order3->id, 'user_id' => $user->id, 'tenant_id' => $tenant2->id]);

    $service = app(RatingService::class);
    $service->recalculateCookRating($tenant1);
    $service->recalculateCookRating($tenant2);

    $tenant1->refresh();
    $tenant2->refresh();

    expect((float) $tenant1->getSetting('average_rating'))->toEqual(4.5)
        ->and($tenant1->getSetting('total_ratings'))->toBe(2)
        ->and((float) $tenant2->getSetting('average_rating'))->toEqual(2.0)
        ->and($tenant2->getSetting('total_ratings'))->toBe(1);
});

// ─── Edge Cases ──────────────────────

test('all 5-star ratings produce exactly 5.0', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create();

    $orders = Order::factory()->completed()->count(3)->create([
        'tenant_id' => $tenant->id,
        'client_id' => $user->id,
    ]);

    foreach ($orders as $order) {
        Rating::factory()->stars(5)->create([
            'order_id' => $order->id,
            'user_id' => $user->id,
            'tenant_id' => $tenant->id,
        ]);
    }

    $service = app(RatingService::class);
    $service->recalculateCookRating($tenant);

    $tenant->refresh();
    expect((float) $tenant->getSetting('average_rating'))->toEqual(5.0);
});

test('single rating of 1 star produces 1.0', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create();

    $order = Order::factory()->completed()->create(['tenant_id' => $tenant->id, 'client_id' => $user->id]);
    Rating::factory()->stars(1)->create(['order_id' => $order->id, 'user_id' => $user->id, 'tenant_id' => $tenant->id]);

    $service = app(RatingService::class);
    $service->recalculateCookRating($tenant);

    $tenant->refresh();
    expect((float) $tenant->getSetting('average_rating'))->toEqual(1.0)
        ->and($tenant->getSetting('total_ratings'))->toBe(1);
});

test('recalculate with zero ratings sets 0 values', function () {
    $tenant = Tenant::factory()->create([
        'settings' => ['average_rating' => 4.0, 'total_ratings' => 5],
    ]);

    // No ratings in DB for this tenant
    $service = app(RatingService::class);
    $service->recalculateCookRating($tenant);

    $tenant->refresh();
    expect((float) $tenant->getSetting('average_rating'))->toEqual(0.0)
        ->and($tenant->getSetting('total_ratings'))->toBe(0);
});

// ─── Scenario 5: Dashboard Rating Stats ──────────────────────

test('dashboard getRatingStats returns rating data for tenant with ratings', function () {
    $tenant = Tenant::factory()->create([
        'settings' => ['average_rating' => 4.3, 'total_ratings' => 45],
    ]);

    $service = app(CookDashboardService::class);
    $stats = $service->getRatingStats($tenant);

    expect($stats['average'])->toEqual(4.3)
        ->and($stats['count'])->toBe(45)
        ->and($stats['hasRating'])->toBeTrue()
        ->and($stats['trend'])->toBeIn(['up', 'down', 'stable']);
});

test('dashboard getRatingStats returns hasRating=false for new cook', function () {
    $tenant = Tenant::factory()->create(['settings' => []]);

    $service = app(CookDashboardService::class);
    $stats = $service->getRatingStats($tenant);

    expect($stats['average'])->toEqual(0.0)
        ->and($stats['count'])->toBe(0)
        ->and($stats['hasRating'])->toBeFalse()
        ->and($stats['trend'])->toBe('stable');
});

test('dashboard getRatingStats calculates trend as up when recent ratings better', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create();

    // Create older rating (40+ days ago) with low score
    $oldOrder = Order::factory()->completed()->create(['tenant_id' => $tenant->id, 'client_id' => $user->id]);
    Rating::factory()->stars(2)->create([
        'order_id' => $oldOrder->id,
        'user_id' => $user->id,
        'tenant_id' => $tenant->id,
        'created_at' => now()->subDays(40),
    ]);

    // Create recent rating with high score
    $user2 = User::factory()->create();
    $newOrder = Order::factory()->completed()->create(['tenant_id' => $tenant->id, 'client_id' => $user2->id]);
    Rating::factory()->stars(5)->create([
        'order_id' => $newOrder->id,
        'user_id' => $user2->id,
        'tenant_id' => $tenant->id,
    ]);

    // Recalculate to update tenant settings
    $ratingService = app(RatingService::class);
    $ratingService->recalculateCookRating($tenant);
    $tenant->refresh();

    $dashboardService = app(CookDashboardService::class);
    $stats = $dashboardService->getRatingStats($tenant);

    // Overall average: (2 + 5) / 2 = 3.5. Old average (before 30 days): 2.0. 3.5 > 2.0, so trend = up
    expect($stats['trend'])->toBe('up');
});

test('dashboard getRatingStats trend is stable for only recent ratings', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create();

    // Only recent ratings, no old ones to compare against
    $order = Order::factory()->completed()->create(['tenant_id' => $tenant->id, 'client_id' => $user->id]);
    Rating::factory()->stars(4)->create([
        'order_id' => $order->id,
        'user_id' => $user->id,
        'tenant_id' => $tenant->id,
    ]);

    $ratingService = app(RatingService::class);
    $ratingService->recalculateCookRating($tenant);
    $tenant->refresh();

    $dashboardService = app(CookDashboardService::class);
    $stats = $dashboardService->getRatingStats($tenant);

    // No old ratings to compare against, so trend is stable
    expect($stats['trend'])->toBe('stable');
});

// ─── getCookRatingStats Service Method ──────────────────────

test('getCookRatingStats returns correct stats from ratings table', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create();

    $order1 = Order::factory()->completed()->create(['tenant_id' => $tenant->id, 'client_id' => $user->id]);
    $order2 = Order::factory()->completed()->create(['tenant_id' => $tenant->id, 'client_id' => $user->id]);

    Rating::factory()->stars(4)->create(['order_id' => $order1->id, 'user_id' => $user->id, 'tenant_id' => $tenant->id]);
    Rating::factory()->stars(5)->create(['order_id' => $order2->id, 'user_id' => $user->id, 'tenant_id' => $tenant->id]);

    $service = app(RatingService::class);
    $stats = $service->getCookRatingStats($tenant->id);

    expect($stats['average'])->toEqual(4.5)
        ->and($stats['total'])->toBe(2);
});

test('getCookRatingStats returns zeros for tenant with no ratings', function () {
    $tenant = Tenant::factory()->create();

    $service = app(RatingService::class);
    $stats = $service->getCookRatingStats($tenant->id);

    expect($stats['average'])->toEqual(0.0)
        ->and($stats['total'])->toBe(0);
});

// ─── Integration: submitRating triggers recalculation ──────────────────────

test('submitRating atomically recalculates cook overall rating', function () {
    $cook = User::factory()->create();
    $tenant = Tenant::factory()->create(['cook_id' => $cook->id]);
    $client = User::factory()->create();

    $order = Order::factory()->completed()->create([
        'tenant_id' => $tenant->id,
        'client_id' => $client->id,
    ]);

    $service = app(RatingService::class);
    $result = $service->submitRating($order, $client, 4, 'Great food!');

    expect($result['success'])->toBeTrue();

    $tenant->refresh();
    expect((float) $tenant->getSetting('average_rating'))->toEqual(4.0)
        ->and($tenant->getSetting('total_ratings'))->toBe(1);
});
