<?php

use App\Models\Order;
use App\Models\Rating;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\RatingReceivedNotification;
use App\Services\RatingService;
use Illuminate\Support\Facades\Notification;

uses(Tests\TestCase::class, Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    test()->seedRolesAndPermissions();
    Notification::fake();
});

// -- Rating Model Tests --

test('rating belongs to order', function () {
    $order = Order::factory()->completed()->create();
    $rating = Rating::factory()->create([
        'order_id' => $order->id,
        'user_id' => $order->client_id,
        'tenant_id' => $order->tenant_id,
    ]);

    expect($rating->order)->toBeInstanceOf(Order::class);
    expect($rating->order->id)->toBe($order->id);
});

test('rating belongs to user', function () {
    $user = User::factory()->create();
    $rating = Rating::factory()->create(['user_id' => $user->id]);

    expect($rating->user)->toBeInstanceOf(User::class);
    expect($rating->user->id)->toBe($user->id);
});

test('rating belongs to tenant', function () {
    $tenant = Tenant::factory()->create();
    $rating = Rating::factory()->create(['tenant_id' => $tenant->id]);

    expect($rating->tenant)->toBeInstanceOf(Tenant::class);
    expect($rating->tenant->id)->toBe($tenant->id);
});

test('order has one rating', function () {
    $order = Order::factory()->completed()->create();
    $rating = Rating::factory()->create([
        'order_id' => $order->id,
        'user_id' => $order->client_id,
        'tenant_id' => $order->tenant_id,
    ]);

    expect($order->rating)->toBeInstanceOf(Rating::class);
    expect($order->rating->id)->toBe($rating->id);
});

test('rating stars is cast to integer', function () {
    $rating = Rating::factory()->stars(4)->create();

    expect($rating->stars)->toBeInt();
    expect($rating->stars)->toBe(4);
});

test('rating min and max constants are defined', function () {
    expect(Rating::MIN_STARS)->toBe(1);
    expect(Rating::MAX_STARS)->toBe(5);
});

// -- RatingService Tests --

test('canRate returns true for completed unrated order owned by user', function () {
    $user = User::factory()->create();
    $order = Order::factory()->completed()->create(['client_id' => $user->id]);

    $service = new RatingService;

    expect($service->canRate($order, $user))->toBeTrue();
});

test('canRate returns false for non-completed order', function () {
    $user = User::factory()->create();
    $order = Order::factory()->paid()->create(['client_id' => $user->id]);

    $service = new RatingService;

    expect($service->canRate($order, $user))->toBeFalse();
});

test('canRate returns false for already rated order', function () {
    $user = User::factory()->create();
    $order = Order::factory()->completed()->create(['client_id' => $user->id]);
    Rating::factory()->create([
        'order_id' => $order->id,
        'user_id' => $user->id,
        'tenant_id' => $order->tenant_id,
    ]);

    $service = new RatingService;

    expect($service->canRate($order, $user))->toBeFalse();
});

test('canRate returns false when user does not own the order', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $order = Order::factory()->completed()->create(['client_id' => $otherUser->id]);

    $service = new RatingService;

    expect($service->canRate($order, $user))->toBeFalse();
});

test('hasBeenRated returns false for unrated order', function () {
    $order = Order::factory()->completed()->create();

    $service = new RatingService;

    expect($service->hasBeenRated($order))->toBeFalse();
});

test('hasBeenRated returns true for rated order', function () {
    $order = Order::factory()->completed()->create();
    Rating::factory()->create([
        'order_id' => $order->id,
        'user_id' => $order->client_id,
        'tenant_id' => $order->tenant_id,
    ]);

    $service = new RatingService;

    expect($service->hasBeenRated($order))->toBeTrue();
});

test('getRating returns null for unrated order', function () {
    $order = Order::factory()->completed()->create();

    $service = new RatingService;

    expect($service->getRating($order))->toBeNull();
});

test('getRating returns rating for rated order', function () {
    $order = Order::factory()->completed()->create();
    $rating = Rating::factory()->stars(5)->create([
        'order_id' => $order->id,
        'user_id' => $order->client_id,
        'tenant_id' => $order->tenant_id,
    ]);

    $service = new RatingService;
    $result = $service->getRating($order);

    expect($result)->toBeInstanceOf(Rating::class);
    expect($result->id)->toBe($rating->id);
    expect($result->stars)->toBe(5);
});

// -- submitRating Tests --

test('submitRating creates rating for valid completed order', function () {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->create(['cook_id' => User::factory()->create()->id]);
    $order = Order::factory()->completed()->create([
        'client_id' => $user->id,
        'tenant_id' => $tenant->id,
        'cook_id' => $tenant->cook_id,
    ]);

    $service = new RatingService;
    $result = $service->submitRating($order, $user, 4);

    expect($result['success'])->toBeTrue();
    expect($result['rating'])->toBeInstanceOf(Rating::class);
    expect($result['rating']->stars)->toBe(4);
    expect($result['rating']->order_id)->toBe($order->id);
    expect($result['rating']->user_id)->toBe($user->id);
    expect($result['rating']->tenant_id)->toBe($tenant->id);
});

test('submitRating fails for non-completed order', function () {
    $user = User::factory()->create();
    $order = Order::factory()->paid()->create(['client_id' => $user->id]);

    $service = new RatingService;
    $result = $service->submitRating($order, $user, 4);

    expect($result['success'])->toBeFalse();
    expect($result['error'])->toContain('completed orders');
});

test('submitRating fails for already rated order (BR-390)', function () {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->create(['cook_id' => User::factory()->create()->id]);
    $order = Order::factory()->completed()->create([
        'client_id' => $user->id,
        'tenant_id' => $tenant->id,
        'cook_id' => $tenant->cook_id,
    ]);
    Rating::factory()->create([
        'order_id' => $order->id,
        'user_id' => $user->id,
        'tenant_id' => $tenant->id,
    ]);

    $service = new RatingService;
    $result = $service->submitRating($order, $user, 5);

    expect($result['success'])->toBeFalse();
    expect($result['error'])->toContain('already been rated');
});

test('submitRating fails when user does not own order', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $order = Order::factory()->completed()->create(['client_id' => $otherUser->id]);

    $service = new RatingService;
    $result = $service->submitRating($order, $user, 4);

    expect($result['success'])->toBeFalse();
    expect($result['error'])->toContain('not authorized');
});

test('submitRating fails for invalid star rating below minimum', function () {
    $user = User::factory()->create();
    $order = Order::factory()->completed()->create(['client_id' => $user->id]);

    $service = new RatingService;
    $result = $service->submitRating($order, $user, 0);

    expect($result['success'])->toBeFalse();
    expect($result['error'])->toContain('between');
});

test('submitRating fails for invalid star rating above maximum', function () {
    $user = User::factory()->create();
    $order = Order::factory()->completed()->create(['client_id' => $user->id]);

    $service = new RatingService;
    $result = $service->submitRating($order, $user, 6);

    expect($result['success'])->toBeFalse();
    expect($result['error'])->toContain('between');
});

test('submitRating accepts 1 star (BR-389 minimum)', function () {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->create(['cook_id' => User::factory()->create()->id]);
    $order = Order::factory()->completed()->create([
        'client_id' => $user->id,
        'tenant_id' => $tenant->id,
        'cook_id' => $tenant->cook_id,
    ]);

    $service = new RatingService;
    $result = $service->submitRating($order, $user, 1);

    expect($result['success'])->toBeTrue();
    expect($result['rating']->stars)->toBe(1);
});

test('submitRating accepts 5 stars (BR-389 maximum)', function () {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->create(['cook_id' => User::factory()->create()->id]);
    $order = Order::factory()->completed()->create([
        'client_id' => $user->id,
        'tenant_id' => $tenant->id,
        'cook_id' => $tenant->cook_id,
    ]);

    $service = new RatingService;
    $result = $service->submitRating($order, $user, 5);

    expect($result['success'])->toBeTrue();
    expect($result['rating']->stars)->toBe(5);
});

// -- Notification Tests (BR-396) --

test('submitRating notifies cook of new rating', function () {
    $cook = User::factory()->create();
    $tenant = Tenant::factory()->create(['cook_id' => $cook->id]);
    $user = User::factory()->create();
    $order = Order::factory()->completed()->create([
        'client_id' => $user->id,
        'tenant_id' => $tenant->id,
        'cook_id' => $cook->id,
    ]);

    $service = new RatingService;
    $service->submitRating($order, $user, 4);

    Notification::assertSentTo($cook, RatingReceivedNotification::class);
});

// -- Cook Rating Recalculation Tests (BR-395) --

test('submitRating recalculates cook average rating', function () {
    $cook = User::factory()->create();
    $tenant = Tenant::factory()->create(['cook_id' => $cook->id]);

    // First rating: 5 stars
    $user1 = User::factory()->create();
    $order1 = Order::factory()->completed()->create([
        'client_id' => $user1->id,
        'tenant_id' => $tenant->id,
        'cook_id' => $cook->id,
    ]);

    $service = new RatingService;
    $service->submitRating($order1, $user1, 5);

    $tenant->refresh();
    expect((float) $tenant->settings['average_rating'])->toBe(5.0);
    expect((int) $tenant->settings['total_ratings'])->toBe(1);

    // Second rating: 3 stars -> average should be 4.0
    $user2 = User::factory()->create();
    $order2 = Order::factory()->completed()->create([
        'client_id' => $user2->id,
        'tenant_id' => $tenant->id,
        'cook_id' => $cook->id,
    ]);
    $service->submitRating($order2, $user2, 3);

    $tenant->refresh();
    expect((float) $tenant->settings['average_rating'])->toBe(4.0);
    expect((int) $tenant->settings['total_ratings'])->toBe(2);
});

test('first rating sets the overall rating (edge case)', function () {
    $cook = User::factory()->create();
    $tenant = Tenant::factory()->create(['cook_id' => $cook->id]);

    // No ratings initially
    expect($tenant->settings['average_rating'] ?? null)->toBeNull();

    $user = User::factory()->create();
    $order = Order::factory()->completed()->create([
        'client_id' => $user->id,
        'tenant_id' => $tenant->id,
        'cook_id' => $cook->id,
    ]);

    $service = new RatingService;
    $service->submitRating($order, $user, 4);

    $tenant->refresh();
    expect((float) $tenant->settings['average_rating'])->toBe(4.0);
    expect((int) $tenant->settings['total_ratings'])->toBe(1);
});

// -- getCookRatingStats Tests --

test('getCookRatingStats returns zero for tenant with no ratings', function () {
    $tenant = Tenant::factory()->create();

    $service = new RatingService;
    $stats = $service->getCookRatingStats($tenant->id);

    expect($stats['average'])->toBe(0.0);
    expect($stats['total'])->toBe(0);
});

test('getCookRatingStats returns correct stats for multiple ratings', function () {
    $tenant = Tenant::factory()->create();

    Rating::factory()->stars(5)->create(['tenant_id' => $tenant->id]);
    Rating::factory()->stars(3)->create(['tenant_id' => $tenant->id]);
    Rating::factory()->stars(4)->create(['tenant_id' => $tenant->id]);

    $service = new RatingService;
    $stats = $service->getCookRatingStats($tenant->id);

    expect($stats['average'])->toBe(4.0);
    expect($stats['total'])->toBe(3);
});

// -- Activity Logging Tests (BR-397) --

test('submitRating creates activity log entry', function () {
    $cook = User::factory()->create();
    $tenant = Tenant::factory()->create(['cook_id' => $cook->id]);
    $user = User::factory()->create();
    $order = Order::factory()->completed()->create([
        'client_id' => $user->id,
        'tenant_id' => $tenant->id,
        'cook_id' => $cook->id,
    ]);

    $service = new RatingService;
    $service->submitRating($order, $user, 4);

    $log = \Spatie\Activitylog\Models\Activity::query()
        ->where('log_name', 'ratings')
        ->where('description', 'submitted rating')
        ->first();

    expect($log)->not->toBeNull();
    expect($log->causer_id)->toBe($user->id);
    expect($log->properties['order_number'])->toBe($order->order_number);
    expect($log->properties['stars'])->toBe(4);
});

// -- Rating notification content tests --

test('RatingReceivedNotification has correct content', function () {
    $cook = User::factory()->create();
    $tenant = Tenant::factory()->create(['cook_id' => $cook->id]);
    $order = Order::factory()->completed()->create([
        'tenant_id' => $tenant->id,
        'cook_id' => $cook->id,
    ]);
    $rating = Rating::factory()->stars(4)->create([
        'order_id' => $order->id,
        'tenant_id' => $tenant->id,
    ]);

    $notification = new RatingReceivedNotification($rating, $order);

    expect($notification->getTitle($cook))->toContain('Rating');
    expect($notification->getBody($cook))->toContain($order->order_number);
    expect($notification->getActionUrl($cook))->toContain('/dashboard/orders/' . $order->id);
    expect($notification->getData($cook)['stars'])->toBe(4);
    expect($notification->getData($cook)['type'])->toBe('rating_received');
});

// -- Edge Case: Rating after refund remains --

test('rating remains after order is refunded', function () {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->create(['cook_id' => User::factory()->create()->id]);
    $order = Order::factory()->completed()->create([
        'client_id' => $user->id,
        'tenant_id' => $tenant->id,
        'cook_id' => $tenant->cook_id,
    ]);

    $service = new RatingService;
    $service->submitRating($order, $user, 4);

    // Simulate refund
    $order->update(['status' => Order::STATUS_REFUNDED]);

    // Rating should still exist
    $rating = $service->getRating($order);
    expect($rating)->not->toBeNull();
    expect($rating->stars)->toBe(4);
});

// -- Edge Case: Multiple meals in one order --

test('one rating per order covers entire experience', function () {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->create(['cook_id' => User::factory()->create()->id]);
    $order = Order::factory()->completed()->withMultipleItems()->create([
        'client_id' => $user->id,
        'tenant_id' => $tenant->id,
        'cook_id' => $tenant->cook_id,
    ]);

    $service = new RatingService;
    $result = $service->submitRating($order, $user, 4);

    expect($result['success'])->toBeTrue();
    expect(Rating::where('order_id', $order->id)->count())->toBe(1);
});

// -- Factory Tests --

test('rating factory creates valid rating', function () {
    $rating = Rating::factory()->create();

    expect($rating)->toBeInstanceOf(Rating::class);
    expect($rating->stars)->toBeGreaterThanOrEqual(1);
    expect($rating->stars)->toBeLessThanOrEqual(5);
    expect($rating->review)->toBeNull();
});

test('rating factory withReview state works', function () {
    $rating = Rating::factory()->withReview()->create();

    expect($rating->review)->toBeString();
    expect($rating->review)->not->toBeEmpty();
});

test('rating factory stars state works', function () {
    $rating = Rating::factory()->stars(3)->create();

    expect($rating->stars)->toBe(3);
});
