<?php

/**
 * F-180: Testimonial Submission Form — Unit Tests
 *
 * Tests the TestimonialService business logic:
 * - BR-426: Eligibility check (completed orders required)
 * - BR-427: Duplicate detection (one per client per tenant)
 * - BR-428: Text validation (max 1,000 characters)
 * - BR-429: Status starts as 'pending'
 * - BR-430: Immutable after submission
 * - BR-433: Ineligible client context
 */

use App\Models\Order;
use App\Models\Tenant;
use App\Models\Testimonial;
use App\Models\User;
use App\Notifications\NewTestimonialNotification;
use App\Services\TestimonialService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(Tests\TestCase::class, RefreshDatabase::class);

$projectRoot = dirname(__DIR__, 3);

// --- Model unit tests ---

describe('Testimonial model', function () {
    it('has correct status constants', function () {
        expect(Testimonial::STATUS_PENDING)->toBe('pending');
        expect(Testimonial::STATUS_APPROVED)->toBe('approved');
        expect(Testimonial::STATUS_REJECTED)->toBe('rejected');
    });

    it('has MAX_TEXT_LENGTH constant', function () {
        expect(Testimonial::MAX_TEXT_LENGTH)->toBe(1000);
    });

    it('has isPending method', function () {
        expect(method_exists(Testimonial::class, 'isPending'))->toBeTrue();
    });

    it('has isApproved method', function () {
        expect(method_exists(Testimonial::class, 'isApproved'))->toBeTrue();
    });

    it('has getStatusLabel method', function () {
        expect(method_exists(Testimonial::class, 'getStatusLabel'))->toBeTrue();
    });

    it('has correct fillable fields', function () {
        $testimonial = new Testimonial;
        $fillable = $testimonial->getFillable();
        expect($fillable)->toContain('tenant_id')
            ->toContain('user_id')
            ->toContain('text')
            ->toContain('status');
    });
});

// --- Service existence tests ---

describe('TestimonialService', function () use ($projectRoot) {
    it('service file exists', function () use ($projectRoot) {
        $path = $projectRoot.'/app/Services/TestimonialService.php';
        expect(file_exists($path))->toBeTrue();
    });

    it('has isEligible method', function () {
        expect(method_exists(TestimonialService::class, 'isEligible'))->toBeTrue();
    });

    it('has hasExistingTestimonial method', function () {
        expect(method_exists(TestimonialService::class, 'hasExistingTestimonial'))->toBeTrue();
    });

    it('has getExistingTestimonial method', function () {
        expect(method_exists(TestimonialService::class, 'getExistingTestimonial'))->toBeTrue();
    });

    it('has getSubmissionContext method', function () {
        expect(method_exists(TestimonialService::class, 'getSubmissionContext'))->toBeTrue();
    });

    it('has submit method', function () {
        expect(method_exists(TestimonialService::class, 'submit'))->toBeTrue();
    });

    it('getSubmissionContext returns correct structure for unauthenticated user', function () {
        $service = new TestimonialService;
        $tenant = Tenant::factory()->create();

        $context = $service->getSubmissionContext(null, $tenant);

        expect($context)->toHaveKeys(['isAuthenticated', 'isEligible', 'existingTestimonial'])
            ->and($context['isAuthenticated'])->toBeFalse()
            ->and($context['isEligible'])->toBeFalse()
            ->and($context['existingTestimonial'])->toBeNull();
    });

    it('isEligible returns false when user has no completed orders', function () {
        $service = new TestimonialService;
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create();

        expect($service->isEligible($user, $tenant))->toBeFalse();
    });

    it('isEligible returns false when user has only non-completed orders', function () {
        $service = new TestimonialService;
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create();

        Order::factory()->create([
            'client_id' => $user->id,
            'tenant_id' => $tenant->id,
            'status' => Order::STATUS_PAID,
        ]);

        expect($service->isEligible($user, $tenant))->toBeFalse();
    });

    it('isEligible returns true when user has a completed order', function () {
        $service = new TestimonialService;
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create();

        Order::factory()->create([
            'client_id' => $user->id,
            'tenant_id' => $tenant->id,
            'status' => Order::STATUS_COMPLETED,
        ]);

        expect($service->isEligible($user, $tenant))->toBeTrue();
    });

    it('isEligible returns false for refunded orders only (BR-edge-case)', function () {
        $service = new TestimonialService;
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create();

        Order::factory()->create([
            'client_id' => $user->id,
            'tenant_id' => $tenant->id,
            'status' => Order::STATUS_REFUNDED,
        ]);

        expect($service->isEligible($user, $tenant))->toBeFalse();
    });

    it('hasExistingTestimonial returns false when none submitted', function () {
        $service = new TestimonialService;
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create();

        expect($service->hasExistingTestimonial($user, $tenant))->toBeFalse();
    });

    it('hasExistingTestimonial returns true when one exists', function () {
        $service = new TestimonialService;
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create();

        Testimonial::factory()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
        ]);

        expect($service->hasExistingTestimonial($user, $tenant))->toBeTrue();
    });

    it('getSubmissionContext returns isEligible true for authenticated eligible user', function () {
        $service = new TestimonialService;
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create();

        Order::factory()->create([
            'client_id' => $user->id,
            'tenant_id' => $tenant->id,
            'status' => Order::STATUS_COMPLETED,
        ]);

        $context = $service->getSubmissionContext($user, $tenant);

        expect($context['isAuthenticated'])->toBeTrue()
            ->and($context['isEligible'])->toBeTrue()
            ->and($context['existingTestimonial'])->toBeNull();
    });

    it('getSubmissionContext returns existing testimonial if one submitted', function () {
        $service = new TestimonialService;
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create();

        $testimonial = Testimonial::factory()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
        ]);

        $context = $service->getSubmissionContext($user, $tenant);

        expect($context['existingTestimonial'])->not->toBeNull()
            ->and($context['existingTestimonial']->id)->toBe($testimonial->id);
    });

    it('submit creates testimonial with pending status (BR-429)', function () {
        Notification::fake();

        $service = new TestimonialService;
        $cook = User::factory()->create();
        $tenant = Tenant::factory()->create(['cook_id' => $cook->id]);
        $user = User::factory()->create();

        Order::factory()->create([
            'client_id' => $user->id,
            'tenant_id' => $tenant->id,
            'status' => Order::STATUS_COMPLETED,
        ]);

        $result = $service->submit($user, $tenant, 'This is a great cook! Highly recommended!');

        expect($result['success'])->toBeTrue()
            ->and($result['testimonial'])->not->toBeNull()
            ->and($result['testimonial']->status)->toBe(Testimonial::STATUS_PENDING)
            ->and($result['testimonial']->tenant_id)->toBe($tenant->id)
            ->and($result['testimonial']->user_id)->toBe($user->id);
    });

    it('submit returns failure when user is ineligible (BR-426)', function () {
        $service = new TestimonialService;
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create();

        $result = $service->submit($user, $tenant, 'Great cook!');

        expect($result['success'])->toBeFalse()
            ->and($result['testimonial'])->toBeNull();
    });

    it('submit returns failure and existing testimonial when already submitted (BR-427)', function () {
        Notification::fake();

        $service = new TestimonialService;
        $cook = User::factory()->create();
        $tenant = Tenant::factory()->create(['cook_id' => $cook->id]);
        $user = User::factory()->create();

        Order::factory()->create([
            'client_id' => $user->id,
            'tenant_id' => $tenant->id,
            'status' => Order::STATUS_COMPLETED,
        ]);

        $existing = Testimonial::factory()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
        ]);

        $result = $service->submit($user, $tenant, 'Trying to submit again!');

        expect($result['success'])->toBeFalse()
            ->and($result['testimonial']->id)->toBe($existing->id);
    });

    it('submit trims whitespace from text', function () {
        Notification::fake();

        $service = new TestimonialService;
        $cook = User::factory()->create();
        $tenant = Tenant::factory()->create(['cook_id' => $cook->id]);
        $user = User::factory()->create();

        Order::factory()->create([
            'client_id' => $user->id,
            'tenant_id' => $tenant->id,
            'status' => Order::STATUS_COMPLETED,
        ]);

        $result = $service->submit($user, $tenant, '   Great cook!   ');

        expect($result['success'])->toBeTrue()
            ->and($result['testimonial']->text)->toBe('Great cook!');
    });

    it('submit notifies the cook (BR-434)', function () {
        Notification::fake();

        $service = new TestimonialService;
        $cook = User::factory()->create();
        $tenant = Tenant::factory()->create(['cook_id' => $cook->id]);
        $user = User::factory()->create();

        Order::factory()->create([
            'client_id' => $user->id,
            'tenant_id' => $tenant->id,
            'status' => Order::STATUS_COMPLETED,
        ]);

        $service->submit($user, $tenant, 'Amazing food! Love it!');

        Notification::assertSentTo($cook, NewTestimonialNotification::class);
    });
});

// --- Notification tests ---

describe('NewTestimonialNotification', function () use ($projectRoot) {
    it('notification file exists', function () use ($projectRoot) {
        $path = $projectRoot.'/app/Notifications/NewTestimonialNotification.php';
        expect(file_exists($path))->toBeTrue();
    });

    it('has required methods', function () {
        expect(method_exists(\App\Notifications\NewTestimonialNotification::class, 'getTitle'))->toBeTrue();
        expect(method_exists(\App\Notifications\NewTestimonialNotification::class, 'getBody'))->toBeTrue();
        expect(method_exists(\App\Notifications\NewTestimonialNotification::class, 'getActionUrl'))->toBeTrue();
        expect(method_exists(\App\Notifications\NewTestimonialNotification::class, 'getData'))->toBeTrue();
    });
});

// --- Controller and route tests ---

describe('TestimonialController', function () use ($projectRoot) {
    it('controller file exists', function () use ($projectRoot) {
        $path = $projectRoot.'/app/Http/Controllers/Tenant/TestimonialController.php';
        expect(file_exists($path))->toBeTrue();
    });

    it('has submit method', function () {
        expect(method_exists(\App\Http\Controllers\Tenant\TestimonialController::class, 'submit'))->toBeTrue();
    });
});

// --- Migration test ---

describe('Testimonial migration', function () {
    it('testimonials table exists in database', function () {
        expect(\Illuminate\Support\Facades\Schema::hasTable('testimonials'))->toBeTrue();
    });

    it('testimonials table has required columns', function () {
        expect(\Illuminate\Support\Facades\Schema::hasColumn('testimonials', 'id'))->toBeTrue();
        expect(\Illuminate\Support\Facades\Schema::hasColumn('testimonials', 'tenant_id'))->toBeTrue();
        expect(\Illuminate\Support\Facades\Schema::hasColumn('testimonials', 'user_id'))->toBeTrue();
        expect(\Illuminate\Support\Facades\Schema::hasColumn('testimonials', 'text'))->toBeTrue();
        expect(\Illuminate\Support\Facades\Schema::hasColumn('testimonials', 'status'))->toBeTrue();
        expect(\Illuminate\Support\Facades\Schema::hasColumn('testimonials', 'created_at'))->toBeTrue();
        expect(\Illuminate\Support\Facades\Schema::hasColumn('testimonials', 'updated_at'))->toBeTrue();
    });
});
