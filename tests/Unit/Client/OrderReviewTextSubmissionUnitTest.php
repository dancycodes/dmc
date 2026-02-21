<?php

use App\Models\Order;
use App\Models\Rating;
use App\Models\Tenant;
use App\Models\User;
use App\Services\RatingService;
use Illuminate\Support\Facades\Notification;

uses(Tests\TestCase::class, Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    test()->seedRolesAndPermissions();
    Notification::fake();
});

// -- Rating Model Constants --

test('rating has MAX_REVIEW_LENGTH constant set to 500', function () {
    expect(Rating::MAX_REVIEW_LENGTH)->toBe(500);
});

// -- submitRating with review text (BR-401) --

test('submitRating saves review text alongside stars', function () {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->create(['cook_id' => User::factory()->create()->id]);
    $order = Order::factory()->completed()->create([
        'client_id' => $user->id,
        'tenant_id' => $tenant->id,
        'cook_id' => $tenant->cook_id,
    ]);

    $service = new RatingService;
    $result = $service->submitRating($order, $user, 5, 'Amazing ndole! Perfectly seasoned and generous portions.');

    expect($result['success'])->toBeTrue();
    expect($result['rating']->stars)->toBe(5);
    expect($result['rating']->review)->toBe('Amazing ndole! Perfectly seasoned and generous portions.');
});

test('submitRating saves rating without review text when null (BR-399)', function () {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->create(['cook_id' => User::factory()->create()->id]);
    $order = Order::factory()->completed()->create([
        'client_id' => $user->id,
        'tenant_id' => $tenant->id,
        'cook_id' => $tenant->cook_id,
    ]);

    $service = new RatingService;
    $result = $service->submitRating($order, $user, 4, null);

    expect($result['success'])->toBeTrue();
    expect($result['rating']->review)->toBeNull();
});

test('submitRating saves rating without review text when empty string (BR-399)', function () {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->create(['cook_id' => User::factory()->create()->id]);
    $order = Order::factory()->completed()->create([
        'client_id' => $user->id,
        'tenant_id' => $tenant->id,
        'cook_id' => $tenant->cook_id,
    ]);

    $service = new RatingService;
    $result = $service->submitRating($order, $user, 4, '');

    expect($result['success'])->toBeTrue();
    expect($result['rating']->review)->toBeNull();
});

// -- Whitespace-only review (edge case) --

test('submitRating treats whitespace-only review as empty', function () {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->create(['cook_id' => User::factory()->create()->id]);
    $order = Order::factory()->completed()->create([
        'client_id' => $user->id,
        'tenant_id' => $tenant->id,
        'cook_id' => $tenant->cook_id,
    ]);

    $service = new RatingService;
    $result = $service->submitRating($order, $user, 4, '   ');

    expect($result['success'])->toBeTrue();
    expect($result['rating']->review)->toBeNull();
});

test('submitRating trims whitespace from review text', function () {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->create(['cook_id' => User::factory()->create()->id]);
    $order = Order::factory()->completed()->create([
        'client_id' => $user->id,
        'tenant_id' => $tenant->id,
        'cook_id' => $tenant->cook_id,
    ]);

    $service = new RatingService;
    $result = $service->submitRating($order, $user, 5, '  Great food!  ');

    expect($result['success'])->toBeTrue();
    expect($result['rating']->review)->toBe('Great food!');
});

// -- Review length validation (BR-400) --

test('submitRating accepts review at exactly 500 characters (BR-400)', function () {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->create(['cook_id' => User::factory()->create()->id]);
    $order = Order::factory()->completed()->create([
        'client_id' => $user->id,
        'tenant_id' => $tenant->id,
        'cook_id' => $tenant->cook_id,
    ]);

    $review = str_repeat('a', 500);

    $service = new RatingService;
    $result = $service->submitRating($order, $user, 4, $review);

    expect($result['success'])->toBeTrue();
    expect(mb_strlen($result['rating']->review))->toBe(500);
});

test('submitRating rejects review exceeding 500 characters (BR-400)', function () {
    $user = User::factory()->create();
    $order = Order::factory()->completed()->create(['client_id' => $user->id]);

    $review = str_repeat('a', 501);

    $service = new RatingService;
    $result = $service->submitRating($order, $user, 4, $review);

    expect($result['success'])->toBeFalse();
    expect($result['error'])->toContain('500');
});

// -- Review with special characters (edge case) --

test('submitRating accepts review with special characters and emojis', function () {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->create(['cook_id' => User::factory()->create()->id]);
    $order = Order::factory()->completed()->create([
        'client_id' => $user->id,
        'tenant_id' => $tenant->id,
        'cook_id' => $tenant->cook_id,
    ]);

    $service = new RatingService;
    $result = $service->submitRating($order, $user, 5, 'Excellent! C\'est magnifique & délicieux <3');

    expect($result['success'])->toBeTrue();
    expect($result['rating']->review)->toBe('Excellent! C\'est magnifique & délicieux <3');
});

// -- Review immutability (BR-402) --

test('review cannot be edited after submission (BR-402)', function () {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->create(['cook_id' => User::factory()->create()->id]);
    $order = Order::factory()->completed()->create([
        'client_id' => $user->id,
        'tenant_id' => $tenant->id,
        'cook_id' => $tenant->cook_id,
    ]);

    $service = new RatingService;
    $service->submitRating($order, $user, 5, 'Great food!');

    // Try to submit again (should fail due to BR-390)
    $result = $service->submitRating($order, $user, 4, 'Changed my mind');

    expect($result['success'])->toBeFalse();
    expect($result['error'])->toContain('already been rated');

    // Original review should be unchanged
    $rating = $service->getRating($order);
    expect($rating->review)->toBe('Great food!');
    expect($rating->stars)->toBe(5);
});

// -- Activity logging includes review info --

test('submitRating activity log includes has_review flag', function () {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->create(['cook_id' => User::factory()->create()->id]);
    $order = Order::factory()->completed()->create([
        'client_id' => $user->id,
        'tenant_id' => $tenant->id,
        'cook_id' => $tenant->cook_id,
    ]);

    $service = new RatingService;
    $service->submitRating($order, $user, 4, 'Tasty meal');

    $log = \Spatie\Activitylog\Models\Activity::query()
        ->where('log_name', 'ratings')
        ->where('description', 'submitted rating')
        ->latest()
        ->first();

    expect($log)->not->toBeNull();
    expect($log->properties['has_review'])->toBeTrue();
});

test('submitRating activity log has_review is false when no review', function () {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->create(['cook_id' => User::factory()->create()->id]);
    $order = Order::factory()->completed()->create([
        'client_id' => $user->id,
        'tenant_id' => $tenant->id,
        'cook_id' => $tenant->cook_id,
    ]);

    $service = new RatingService;
    $service->submitRating($order, $user, 3, null);

    $log = \Spatie\Activitylog\Models\Activity::query()
        ->where('log_name', 'ratings')
        ->where('description', 'submitted rating')
        ->latest()
        ->first();

    expect($log)->not->toBeNull();
    expect($log->properties['has_review'])->toBeFalse();
});

// -- Factory withReview state --

test('rating factory withReview creates rating with review text', function () {
    $rating = Rating::factory()->withReview('Custom review text')->create();

    expect($rating->review)->toBe('Custom review text');
});

test('rating factory withReview creates rating with random review', function () {
    $rating = Rating::factory()->withReview()->create();

    expect($rating->review)->toBeString();
    expect($rating->review)->not->toBeEmpty();
});

// -- Backward compatibility (F-176 without review parameter) --

test('submitRating works without review parameter for backward compatibility', function () {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->create(['cook_id' => User::factory()->create()->id]);
    $order = Order::factory()->completed()->create([
        'client_id' => $user->id,
        'tenant_id' => $tenant->id,
        'cook_id' => $tenant->cook_id,
    ]);

    $service = new RatingService;
    // Call without review parameter (F-176 backward compat)
    $result = $service->submitRating($order, $user, 4);

    expect($result['success'])->toBeTrue();
    expect($result['rating']->review)->toBeNull();
});

// -- StoreRatingRequest validation rules --

test('StoreRatingRequest includes review_text validation rule', function () {
    $request = new \App\Http\Requests\Client\StoreRatingRequest;
    $rules = $request->rules();

    expect($rules)->toHaveKey('review_text');
    expect($rules['review_text'])->toContain('nullable');
    expect($rules['review_text'])->toContain('string');
    expect($rules['review_text'])->toContain('max:500');
});

test('StoreRatingRequest has custom message for review_text max', function () {
    $request = new \App\Http\Requests\Client\StoreRatingRequest;
    $messages = $request->messages();

    expect($messages)->toHaveKey('review_text.max');
    expect($messages['review_text.max'])->toContain('500');
});
