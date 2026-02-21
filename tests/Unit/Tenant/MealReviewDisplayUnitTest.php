<?php

/**
 * F-178: Rating & Review Display on Meal — Unit Tests
 *
 * Tests RatingService methods for meal review display:
 * - getMealRatingStats: average, total, distribution
 * - getMealReviews: paginated, sorted newest first
 * - formatReviewForDisplay: client name, stars, date
 * - formatClientName: privacy (first name + last initial)
 * - getMealReviewDisplayData: complete review display data
 */

use App\Models\Meal;
use App\Models\Order;
use App\Models\Rating;
use App\Models\Tenant;
use App\Models\User;
use App\Services\RatingService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    (new RoleAndPermissionSeeder)->run();
    $this->ratingService = new RatingService;
});

// ============================================================
// formatClientName — BR-415: Client name privacy
// ============================================================

test('formatClientName returns first name and last initial', function () {
    $result = $this->ratingService->formatClientName('Amara Ndongo');
    expect($result)->toBe('Amara N.');
});

test('formatClientName handles single name', function () {
    $result = $this->ratingService->formatClientName('Amara');
    expect($result)->toBe('Amara');
});

test('formatClientName handles multiple names (uses last)', function () {
    $result = $this->ratingService->formatClientName('Jean Pierre Ndongo');
    expect($result)->toBe('Jean N.');
});

test('formatClientName returns Anonymous for null', function () {
    $result = $this->ratingService->formatClientName(null);
    expect($result)->toBe('Anonymous');
});

test('formatClientName trims whitespace', function () {
    $result = $this->ratingService->formatClientName('  Amara  Ndongo  ');
    expect($result)->toBe('Amara N.');
});

test('formatClientName handles unicode names', function () {
    $result = $this->ratingService->formatClientName('Émilie Fôntaine');
    expect($result)->toBe('Émilie F.');
});

// ============================================================
// formatReviewForDisplay — BR-411: Review display format
// ============================================================

test('formatReviewForDisplay returns correct structure with review text', function () {
    $user = User::factory()->create(['name' => 'Amara Ndongo']);
    $tenant = Tenant::factory()->create();
    $order = Order::factory()->completed()->create([
        'client_id' => $user->id,
        'tenant_id' => $tenant->id,
        'items_snapshot' => [['meal_id' => 1, 'meal_name' => 'Test Meal', 'component_id' => 1, 'component_name' => 'Standard', 'quantity' => 1, 'unit_price' => 1000, 'subtotal' => 1000]],
    ]);

    $rating = Rating::factory()->withReview('Amazing food!')->create([
        'order_id' => $order->id,
        'user_id' => $user->id,
        'tenant_id' => $tenant->id,
        'stars' => 5,
    ]);
    $rating->load('user');

    $result = $this->ratingService->formatReviewForDisplay($rating);

    expect($result)
        ->toHaveKeys(['id', 'stars', 'review', 'date', 'relativeDate', 'clientName'])
        ->and($result['stars'])->toBe(5)
        ->and($result['review'])->toBe('Amazing food!')
        ->and($result['clientName'])->toBe('Amara N.');
});

test('formatReviewForDisplay handles rating without review text', function () {
    $user = User::factory()->create(['name' => 'Paul Biya']);
    $tenant = Tenant::factory()->create();
    $order = Order::factory()->completed()->create([
        'client_id' => $user->id,
        'tenant_id' => $tenant->id,
    ]);

    $rating = Rating::factory()->create([
        'order_id' => $order->id,
        'user_id' => $user->id,
        'tenant_id' => $tenant->id,
        'stars' => 3,
        'review' => null,
    ]);
    $rating->load('user');

    $result = $this->ratingService->formatReviewForDisplay($rating);

    expect($result['review'])->toBeNull()
        ->and($result['stars'])->toBe(3)
        ->and($result['clientName'])->toBe('Paul B.');
});

// ============================================================
// getMealRatingStats — BR-408/BR-409/BR-410: Average & distribution
// ============================================================

test('getMealRatingStats returns zeros for meal with no ratings', function () {
    $tenant = Tenant::factory()->create();
    $result = $this->ratingService->getMealRatingStats(999, $tenant->id);

    expect($result['average'])->toBe(0.0)
        ->and($result['total'])->toBe(0)
        ->and($result['distribution'])->toBe([5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0]);
});

test('getMealRatingStats calculates correct average for meal', function () {
    $tenant = Tenant::factory()->create();
    $meal = Meal::factory()->create(['tenant_id' => $tenant->id]);

    // Create orders with this meal in items_snapshot
    $users = User::factory()->count(3)->create();
    $orders = collect();
    foreach ($users as $user) {
        $orders->push(Order::factory()->completed()->create([
            'client_id' => $user->id,
            'tenant_id' => $tenant->id,
            'items_snapshot' => [['meal_id' => $meal->id, 'meal_name' => 'Test', 'component_id' => 1, 'component_name' => '', 'quantity' => 1, 'unit_price' => 1000, 'subtotal' => 1000]],
        ]));
    }

    // Create ratings: 5, 4, 3 stars => average 4.0
    Rating::factory()->stars(5)->create(['order_id' => $orders[0]->id, 'user_id' => $users[0]->id, 'tenant_id' => $tenant->id]);
    Rating::factory()->stars(4)->create(['order_id' => $orders[1]->id, 'user_id' => $users[1]->id, 'tenant_id' => $tenant->id]);
    Rating::factory()->stars(3)->create(['order_id' => $orders[2]->id, 'user_id' => $users[2]->id, 'tenant_id' => $tenant->id]);

    $result = $this->ratingService->getMealRatingStats($meal->id, $tenant->id);

    expect($result['average'])->toBe(4.0)
        ->and($result['total'])->toBe(3)
        ->and($result['distribution'][5])->toBe(1)
        ->and($result['distribution'][4])->toBe(1)
        ->and($result['distribution'][3])->toBe(1)
        ->and($result['distribution'][2])->toBe(0)
        ->and($result['distribution'][1])->toBe(0);
});

test('getMealRatingStats with single rating shows that rating as average', function () {
    $tenant = Tenant::factory()->create();
    $meal = Meal::factory()->create(['tenant_id' => $tenant->id]);
    $user = User::factory()->create();

    $order = Order::factory()->completed()->create([
        'client_id' => $user->id,
        'tenant_id' => $tenant->id,
        'items_snapshot' => [['meal_id' => $meal->id, 'meal_name' => 'Test', 'component_id' => 1, 'component_name' => '', 'quantity' => 1, 'unit_price' => 1000, 'subtotal' => 1000]],
    ]);

    Rating::factory()->stars(4)->create(['order_id' => $order->id, 'user_id' => $user->id, 'tenant_id' => $tenant->id]);

    $result = $this->ratingService->getMealRatingStats($meal->id, $tenant->id);

    expect($result['average'])->toBe(4.0)
        ->and($result['total'])->toBe(1);
});

test('getMealRatingStats only counts ratings for orders containing the specified meal', function () {
    $tenant = Tenant::factory()->create();
    $meal1 = Meal::factory()->create(['tenant_id' => $tenant->id]);
    $meal2 = Meal::factory()->create(['tenant_id' => $tenant->id]);

    $user = User::factory()->create();

    // Order for meal1
    $order1 = Order::factory()->completed()->create([
        'client_id' => $user->id,
        'tenant_id' => $tenant->id,
        'items_snapshot' => [['meal_id' => $meal1->id, 'meal_name' => 'Meal 1', 'component_id' => 1, 'component_name' => '', 'quantity' => 1, 'unit_price' => 1000, 'subtotal' => 1000]],
    ]);
    Rating::factory()->stars(5)->create(['order_id' => $order1->id, 'user_id' => $user->id, 'tenant_id' => $tenant->id]);

    // Order for meal2 (should not be counted for meal1)
    $user2 = User::factory()->create();
    $order2 = Order::factory()->completed()->create([
        'client_id' => $user2->id,
        'tenant_id' => $tenant->id,
        'items_snapshot' => [['meal_id' => $meal2->id, 'meal_name' => 'Meal 2', 'component_id' => 2, 'component_name' => '', 'quantity' => 1, 'unit_price' => 2000, 'subtotal' => 2000]],
    ]);
    Rating::factory()->stars(1)->create(['order_id' => $order2->id, 'user_id' => $user2->id, 'tenant_id' => $tenant->id]);

    $result = $this->ratingService->getMealRatingStats($meal1->id, $tenant->id);

    expect($result['average'])->toBe(5.0)
        ->and($result['total'])->toBe(1);
});

test('getMealRatingStats includes rating for multi-meal orders', function () {
    $tenant = Tenant::factory()->create();
    $meal1 = Meal::factory()->create(['tenant_id' => $tenant->id]);
    $meal2 = Meal::factory()->create(['tenant_id' => $tenant->id]);
    $user = User::factory()->create();

    // Order containing both meals
    $order = Order::factory()->completed()->create([
        'client_id' => $user->id,
        'tenant_id' => $tenant->id,
        'items_snapshot' => [
            ['meal_id' => $meal1->id, 'meal_name' => 'Meal 1', 'component_id' => 1, 'component_name' => '', 'quantity' => 1, 'unit_price' => 1000, 'subtotal' => 1000],
            ['meal_id' => $meal2->id, 'meal_name' => 'Meal 2', 'component_id' => 2, 'component_name' => '', 'quantity' => 1, 'unit_price' => 2000, 'subtotal' => 2000],
        ],
    ]);

    Rating::factory()->stars(4)->create(['order_id' => $order->id, 'user_id' => $user->id, 'tenant_id' => $tenant->id]);

    // The rating should appear on BOTH meal pages
    $result1 = $this->ratingService->getMealRatingStats($meal1->id, $tenant->id);
    $result2 = $this->ratingService->getMealRatingStats($meal2->id, $tenant->id);

    expect($result1['total'])->toBe(1)
        ->and($result1['average'])->toBe(4.0)
        ->and($result2['total'])->toBe(1)
        ->and($result2['average'])->toBe(4.0);
});

// ============================================================
// getMealReviews — BR-412/BR-413: Sorted and paginated
// ============================================================

test('getMealReviews returns paginated reviews sorted newest first', function () {
    $tenant = Tenant::factory()->create();
    $meal = Meal::factory()->create(['tenant_id' => $tenant->id]);

    // Create 3 reviews at different times
    $users = User::factory()->count(3)->create();
    $dates = [now()->subDays(3), now()->subDays(1), now()];

    foreach ($users as $i => $user) {
        $order = Order::factory()->completed()->create([
            'client_id' => $user->id,
            'tenant_id' => $tenant->id,
            'items_snapshot' => [['meal_id' => $meal->id, 'meal_name' => 'Test', 'component_id' => 1, 'component_name' => '', 'quantity' => 1, 'unit_price' => 1000, 'subtotal' => 1000]],
        ]);
        Rating::factory()->create([
            'order_id' => $order->id,
            'user_id' => $user->id,
            'tenant_id' => $tenant->id,
            'stars' => $i + 3,
            'created_at' => $dates[$i],
            'updated_at' => $dates[$i],
        ]);
    }

    $result = $this->ratingService->getMealReviews($meal->id, $tenant->id);

    expect($result->total())->toBe(3)
        ->and($result->first()->stars)->toBe(5) // newest (today) = 5 stars
        ->and($result->last()->stars)->toBe(3); // oldest (3 days ago) = 3 stars
});

test('getMealReviews paginates with 10 per page', function () {
    $tenant = Tenant::factory()->create();
    $meal = Meal::factory()->create(['tenant_id' => $tenant->id]);

    // Create 15 reviews
    for ($i = 0; $i < 15; $i++) {
        $user = User::factory()->create();
        $order = Order::factory()->completed()->create([
            'client_id' => $user->id,
            'tenant_id' => $tenant->id,
            'items_snapshot' => [['meal_id' => $meal->id, 'meal_name' => 'Test', 'component_id' => 1, 'component_name' => '', 'quantity' => 1, 'unit_price' => 1000, 'subtotal' => 1000]],
        ]);
        Rating::factory()->create([
            'order_id' => $order->id,
            'user_id' => $user->id,
            'tenant_id' => $tenant->id,
        ]);
    }

    $page1 = $this->ratingService->getMealReviews($meal->id, $tenant->id, 1);
    $page2 = $this->ratingService->getMealReviews($meal->id, $tenant->id, 2);

    expect($page1->count())->toBe(10)
        ->and($page1->total())->toBe(15)
        ->and($page1->hasMorePages())->toBeTrue()
        ->and($page2->count())->toBe(5)
        ->and($page2->hasMorePages())->toBeFalse();
});

test('getMealReviews includes user relationship for name display', function () {
    $tenant = Tenant::factory()->create();
    $meal = Meal::factory()->create(['tenant_id' => $tenant->id]);
    $user = User::factory()->create(['name' => 'Test User']);
    $order = Order::factory()->completed()->create([
        'client_id' => $user->id,
        'tenant_id' => $tenant->id,
        'items_snapshot' => [['meal_id' => $meal->id, 'meal_name' => 'Test', 'component_id' => 1, 'component_name' => '', 'quantity' => 1, 'unit_price' => 1000, 'subtotal' => 1000]],
    ]);
    Rating::factory()->create([
        'order_id' => $order->id,
        'user_id' => $user->id,
        'tenant_id' => $tenant->id,
    ]);

    $result = $this->ratingService->getMealReviews($meal->id, $tenant->id);

    expect($result->first()->user)->not->toBeNull()
        ->and($result->first()->user->name)->toBe('Test User');
});

// ============================================================
// getMealReviewDisplayData — Complete display data
// ============================================================

test('getMealReviewDisplayData returns stats, reviews, and pagination', function () {
    $tenant = Tenant::factory()->create();
    $meal = Meal::factory()->create(['tenant_id' => $tenant->id]);
    $user = User::factory()->create(['name' => 'Marie Fontem']);
    $order = Order::factory()->completed()->create([
        'client_id' => $user->id,
        'tenant_id' => $tenant->id,
        'items_snapshot' => [['meal_id' => $meal->id, 'meal_name' => 'Test', 'component_id' => 1, 'component_name' => '', 'quantity' => 1, 'unit_price' => 1000, 'subtotal' => 1000]],
    ]);
    Rating::factory()->withReview('Delicious!')->stars(5)->create([
        'order_id' => $order->id,
        'user_id' => $user->id,
        'tenant_id' => $tenant->id,
    ]);

    $result = $this->ratingService->getMealReviewDisplayData($meal->id, $tenant->id);

    expect($result)
        ->toHaveKeys(['stats', 'reviews', 'pagination'])
        ->and($result['stats']['average'])->toBe(5.0)
        ->and($result['stats']['total'])->toBe(1)
        ->and($result['reviews'])->toHaveCount(1)
        ->and($result['reviews'][0]['clientName'])->toBe('Marie F.')
        ->and($result['reviews'][0]['review'])->toBe('Delicious!')
        ->and($result['reviews'][0]['stars'])->toBe(5)
        ->and($result['pagination']['hasMore'])->toBeFalse();
});

test('getMealReviewDisplayData returns empty state for meal with no reviews', function () {
    $tenant = Tenant::factory()->create();
    $meal = Meal::factory()->create(['tenant_id' => $tenant->id]);

    $result = $this->ratingService->getMealReviewDisplayData($meal->id, $tenant->id);

    expect($result['stats']['total'])->toBe(0)
        ->and($result['stats']['average'])->toBe(0.0)
        ->and($result['reviews'])->toBeEmpty()
        ->and($result['pagination']['hasMore'])->toBeFalse();
});

test('getMealReviewDisplayData handles deactivated user reviews', function () {
    $tenant = Tenant::factory()->create();
    $meal = Meal::factory()->create(['tenant_id' => $tenant->id]);
    $user = User::factory()->create(['name' => 'Inactive Person', 'is_active' => false]);
    $order = Order::factory()->completed()->create([
        'client_id' => $user->id,
        'tenant_id' => $tenant->id,
        'items_snapshot' => [['meal_id' => $meal->id, 'meal_name' => 'Test', 'component_id' => 1, 'component_name' => '', 'quantity' => 1, 'unit_price' => 1000, 'subtotal' => 1000]],
    ]);
    Rating::factory()->withReview('Still visible')->create([
        'order_id' => $order->id,
        'user_id' => $user->id,
        'tenant_id' => $tenant->id,
    ]);

    $result = $this->ratingService->getMealReviewDisplayData($meal->id, $tenant->id);

    // Edge case: deactivated client review remains visible
    expect($result['reviews'])->toHaveCount(1)
        ->and($result['reviews'][0]['clientName'])->toBe('Inactive P.');
});

test('getMealReviewDisplayData shows same client multiple ratings from different orders', function () {
    $tenant = Tenant::factory()->create();
    $meal = Meal::factory()->create(['tenant_id' => $tenant->id]);
    $user = User::factory()->create(['name' => 'Repeat Customer']);

    // Two separate orders for the same meal
    for ($i = 0; $i < 2; $i++) {
        $order = Order::factory()->completed()->create([
            'client_id' => $user->id,
            'tenant_id' => $tenant->id,
            'items_snapshot' => [['meal_id' => $meal->id, 'meal_name' => 'Test', 'component_id' => 1, 'component_name' => '', 'quantity' => 1, 'unit_price' => 1000, 'subtotal' => 1000]],
        ]);
        Rating::factory()->stars($i + 4)->create([
            'order_id' => $order->id,
            'user_id' => $user->id,
            'tenant_id' => $tenant->id,
        ]);
    }

    $result = $this->ratingService->getMealReviewDisplayData($meal->id, $tenant->id);

    expect($result['reviews'])->toHaveCount(2)
        ->and($result['stats']['total'])->toBe(2);
});

// ============================================================
// REVIEWS_PER_PAGE constant
// ============================================================

test('REVIEWS_PER_PAGE is 10', function () {
    expect(RatingService::REVIEWS_PER_PAGE)->toBe(10);
});
