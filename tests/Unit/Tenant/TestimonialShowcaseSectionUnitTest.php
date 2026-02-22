<?php

/**
 * F-131: Testimonials Showcase Section — Unit Tests
 *
 * Tests the showcase-specific business rules for displaying
 * approved testimonials on the tenant landing page.
 *
 * BR-176: Only cook-approved testimonials are displayed.
 * BR-177: Maximum 10 testimonials displayed on the landing page.
 * BR-178: Each card shows client first name, date submitted, testimonial text.
 * BR-179: Testimonials sorted by approved_at (most recent first).
 * BR-180: "Submit Testimonial" available only to authenticated clients.
 * BR-181: Submitted testimonials go to pending state; require cook approval.
 * BR-182: Guest users see testimonials but cannot submit; they see a login prompt.
 * BR-183: Testimonial text has a maximum length of 500 characters (spec = 500; model = 1000 per F-180 BR-428).
 * BR-184: All text in this section must be localized via __().
 * BR-185: Testimonial submission uses Gale (no page reload).
 */

use App\Models\Order;
use App\Models\Tenant;
use App\Models\Testimonial;
use App\Models\User;
use App\Services\TestimonialService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

// ----------------------------------------------------------------
// BR-176: Only approved testimonials displayed
// ----------------------------------------------------------------

describe('BR-176: Only approved testimonials displayed in showcase', function () {
    it('excludes pending testimonials from showcase display', function () {
        $service = new TestimonialService;
        $tenant = Tenant::factory()->create();

        Testimonial::factory()->count(3)->create([
            'tenant_id' => $tenant->id,
            'status' => Testimonial::STATUS_PENDING,
        ]);

        $result = $service->getApprovedTestimonialsForDisplay($tenant);

        expect($result['hasTestimonials'])->toBeFalse()
            ->and($result['testimonials'])->toHaveCount(0);
    });

    it('excludes rejected testimonials from showcase display', function () {
        $service = new TestimonialService;
        $tenant = Tenant::factory()->create();

        Testimonial::factory()->count(2)->create([
            'tenant_id' => $tenant->id,
            'status' => Testimonial::STATUS_REJECTED,
        ]);

        $result = $service->getApprovedTestimonialsForDisplay($tenant);

        expect($result['hasTestimonials'])->toBeFalse()
            ->and($result['testimonials'])->toHaveCount(0);
    });

    it('includes only approved testimonials in showcase display', function () {
        $service = new TestimonialService;
        $tenant = Tenant::factory()->create();

        Testimonial::factory()->count(2)->create([
            'tenant_id' => $tenant->id,
            'status' => Testimonial::STATUS_APPROVED,
        ]);
        Testimonial::factory()->create([
            'tenant_id' => $tenant->id,
            'status' => Testimonial::STATUS_PENDING,
        ]);

        $result = $service->getApprovedTestimonialsForDisplay($tenant);

        expect($result['testimonials'])->toHaveCount(2);
        foreach ($result['testimonials'] as $t) {
            expect($t->status)->toBe(Testimonial::STATUS_APPROVED);
        }
    });
});

// ----------------------------------------------------------------
// BR-177: Maximum 10 testimonials displayed
// ----------------------------------------------------------------

describe('BR-177: Maximum 10 testimonials displayed on landing page', function () {
    it('shows all testimonials when 8 are approved (accepts 8)', function () {
        $service = new TestimonialService;
        $tenant = Tenant::factory()->create();

        Testimonial::factory()->count(8)->create([
            'tenant_id' => $tenant->id,
            'status' => Testimonial::STATUS_APPROVED,
        ]);

        $result = $service->getApprovedTestimonialsForDisplay($tenant);

        expect($result['testimonials'])->toHaveCount(8)
            ->and($result['hasTestimonials'])->toBeTrue();
    });

    it('caps display at 10 when 15 are approved with no featured selection', function () {
        $service = new TestimonialService;
        $tenant = Tenant::factory()->create();

        Testimonial::factory()->count(15)->create([
            'tenant_id' => $tenant->id,
            'status' => Testimonial::STATUS_APPROVED,
            'is_featured' => false,
        ]);

        $result = $service->getApprovedTestimonialsForDisplay($tenant);

        expect($result['testimonials'])->toHaveCount(10)
            ->and($result['totalApproved'])->toBe(15);
    });

    it('has MAX_DISPLAY_COUNT constant equal to 10', function () {
        expect(Testimonial::MAX_DISPLAY_COUNT)->toBe(10);
    });
});

// ----------------------------------------------------------------
// BR-178: Each testimonial card data (client first name, date, text)
// ----------------------------------------------------------------

describe('BR-178: Testimonial card data', function () {
    it('getClientDisplayName returns first name + last initial for showcase', function () {
        $user = User::factory()->make(['name' => 'Marie Nguele']);
        $testimonial = Testimonial::factory()->make(['text' => 'Great food!']);
        $testimonial->setRelation('user', $user);

        expect($testimonial->getClientDisplayName())->toBe('Marie N.');
    });

    it('has approved_at date attribute for display on card', function () {
        $testimonial = Testimonial::factory()->make([
            'approved_at' => now()->subDays(5),
        ]);

        expect($testimonial->approved_at)->not->toBeNull();
    });

    it('testimonial text is accessible as text property', function () {
        $text = 'This cook makes the best ndole in town!';
        $testimonial = Testimonial::factory()->make(['text' => $text]);

        expect($testimonial->text)->toBe($text);
    });

    it('casts approved_at as datetime', function () {
        $testimonial = Testimonial::factory()->make([
            'approved_at' => now()->subDays(2),
        ]);

        expect($testimonial->approved_at)->toBeInstanceOf(\Illuminate\Support\Carbon::class);
    });
});

// ----------------------------------------------------------------
// BR-179: Sorted by approved_at (most recent first)
// ----------------------------------------------------------------

describe('BR-179: Testimonials sorted by approved_at desc', function () {
    it('returns testimonials ordered by approved_at descending', function () {
        $service = new TestimonialService;
        $tenant = Tenant::factory()->create();

        $oldest = Testimonial::factory()->create([
            'tenant_id' => $tenant->id,
            'status' => Testimonial::STATUS_APPROVED,
            'approved_at' => now()->subDays(10),
        ]);

        $newest = Testimonial::factory()->create([
            'tenant_id' => $tenant->id,
            'status' => Testimonial::STATUS_APPROVED,
            'approved_at' => now()->subDay(),
        ]);

        $middle = Testimonial::factory()->create([
            'tenant_id' => $tenant->id,
            'status' => Testimonial::STATUS_APPROVED,
            'approved_at' => now()->subDays(5),
        ]);

        $result = $service->getApprovedTestimonialsForDisplay($tenant);
        $ids = $result['testimonials']->pluck('id')->toArray();

        expect($ids[0])->toBe($newest->id)
            ->and($ids[1])->toBe($middle->id)
            ->and($ids[2])->toBe($oldest->id);
    });
});

// ----------------------------------------------------------------
// BR-180: Submit Testimonial only available to authenticated clients
// ----------------------------------------------------------------

describe('BR-180 / BR-182: Authentication context for showcase submission', function () {
    it('getSubmissionContext returns isAuthenticated=false for guest', function () {
        $service = new TestimonialService;
        $tenant = Tenant::factory()->create();

        $context = $service->getSubmissionContext(null, $tenant);

        expect($context['isAuthenticated'])->toBeFalse()
            ->and($context['isEligible'])->toBeFalse()
            ->and($context['existingTestimonial'])->toBeNull();
    });

    it('getSubmissionContext returns isAuthenticated=true for logged-in user', function () {
        $service = new TestimonialService;
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create();

        $context = $service->getSubmissionContext($user, $tenant);

        expect($context['isAuthenticated'])->toBeTrue();
    });
});

// ----------------------------------------------------------------
// BR-181: Submitted testimonials go to pending state
// ----------------------------------------------------------------

describe('BR-181: Submitted testimonials start as pending', function () {
    it('has STATUS_PENDING constant defined', function () {
        expect(Testimonial::STATUS_PENDING)->toBe('pending');
    });

    it('factory default state is pending', function () {
        $testimonial = Testimonial::factory()->make();

        expect($testimonial->status)->toBe(Testimonial::STATUS_PENDING);
    });

    it('submit method creates testimonial with pending status', function () {
        $service = new TestimonialService;
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create();

        // Eligible user: has completed order
        Order::factory()->create([
            'client_id' => $user->id,
            'tenant_id' => $tenant->id,
            'status' => Order::STATUS_COMPLETED,
        ]);

        $result = $service->submit($user, $tenant, 'Great food, very tasty!');

        expect($result['success'])->toBeTrue()
            ->and($result['testimonial']->status)->toBe(Testimonial::STATUS_PENDING);
    });
});

// ----------------------------------------------------------------
// BR-183: Maximum text length
// ----------------------------------------------------------------

describe('BR-183 / model: Testimonial text length constant', function () {
    it('has MAX_TEXT_LENGTH constant defined', function () {
        expect(Testimonial::MAX_TEXT_LENGTH)->toBeInt()
            ->and(Testimonial::MAX_TEXT_LENGTH)->toBeGreaterThanOrEqual(500);
    });

    it('max_text_length is at least 500 characters', function () {
        expect(Testimonial::MAX_TEXT_LENGTH)->toBeGreaterThanOrEqual(500);
    });
});

// ----------------------------------------------------------------
// View file existence checks
// ----------------------------------------------------------------

describe('F-131: Required view files exist', function () {
    it('testimonials section partial exists on tenant home', function () {
        $projectRoot = dirname(__DIR__, 3);
        expect(file_exists($projectRoot.'/resources/views/tenant/home.blade.php'))->toBeTrue();
    });

    it('testimonials display partial exists', function () {
        $projectRoot = dirname(__DIR__, 3);
        expect(file_exists($projectRoot.'/resources/views/tenant/_testimonials-display.blade.php'))->toBeTrue();
    });

    it('testimonial card partial exists', function () {
        $projectRoot = dirname(__DIR__, 3);
        expect(file_exists($projectRoot.'/resources/views/tenant/_testimonial-card.blade.php'))->toBeTrue();
    });

    it('home view contains testimonials section anchor', function () {
        $projectRoot = dirname(__DIR__, 3);
        $content = file_get_contents($projectRoot.'/resources/views/tenant/home.blade.php');

        expect($content)->toContain('id="testimonials"');
    });

    it('home view includes the testimonials display partial', function () {
        $projectRoot = dirname(__DIR__, 3);
        $content = file_get_contents($projectRoot.'/resources/views/tenant/home.blade.php');

        expect($content)->toContain('_testimonials-display');
    });

    it('testimonials display partial uses Gale action for submission', function () {
        $projectRoot = dirname(__DIR__, 3);
        $content = file_get_contents($projectRoot.'/resources/views/tenant/_testimonials-display.blade.php');

        expect($content)->toContain('$action(');
    });

    it('testimonials display partial has localized section header text', function () {
        $projectRoot = dirname(__DIR__, 3);

        // Section header is in home.blade.php
        $homeContent = file_get_contents($projectRoot.'/resources/views/tenant/home.blade.php');
        expect($homeContent)->toContain("__('What Our Customers Say')");
    });
});
