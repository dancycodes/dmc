<?php

/**
 * F-182: Approved Testimonials Display — Unit Tests
 *
 * Tests the display-related business logic:
 * - BR-446: Only approved testimonials are displayed
 * - BR-447: Maximum 10 testimonials displayed at a time
 * - BR-448: Featured selection when > 10 approved
 * - BR-449: Client display name (first name + last initial)
 * - BR-453: Testimonials are tenant-scoped
 */

use App\Models\Tenant;
use App\Models\Testimonial;
use App\Models\User;
use App\Services\TestimonialService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

// --- Testimonial model display methods ---

describe('Testimonial::getClientDisplayName (BR-449)', function () {
    it('returns first name and last initial for multi-word names', function () {
        $user = User::factory()->make(['name' => 'John Doe']);
        $testimonial = Testimonial::factory()->make();
        $testimonial->setRelation('user', $user);

        expect($testimonial->getClientDisplayName())->toBe('John D.');
    });

    it('returns single name as-is when no last name', function () {
        $user = User::factory()->make(['name' => 'Latifa']);
        $testimonial = Testimonial::factory()->make();
        $testimonial->setRelation('user', $user);

        expect($testimonial->getClientDisplayName())->toBe('Latifa');
    });

    it('handles three-part names using last word for initial', function () {
        $user = User::factory()->make(['name' => 'Jean Paul Mbarga']);
        $testimonial = Testimonial::factory()->make();
        $testimonial->setRelation('user', $user);

        expect($testimonial->getClientDisplayName())->toBe('Jean M.');
    });

    it('returns Former User when user relation is null', function () {
        $testimonial = Testimonial::factory()->make();
        $testimonial->setRelation('user', null);

        expect($testimonial->getClientDisplayName())->toBe('Former User');
    });

    it('returns Former User when user name is empty string', function () {
        $user = User::factory()->make(['name' => '']);
        $testimonial = Testimonial::factory()->make();
        $testimonial->setRelation('user', $user);

        expect($testimonial->getClientDisplayName())->toBe('Former User');
    });

    it('returns Former User when user name is whitespace only', function () {
        $user = User::factory()->make(['name' => '   ']);
        $testimonial = Testimonial::factory()->make();
        $testimonial->setRelation('user', $user);

        expect($testimonial->getClientDisplayName())->toBe('Former User');
    });
});

describe('Testimonial model constants and methods (BR-447)', function () {
    it('has MAX_DISPLAY_COUNT constant of 10', function () {
        expect(Testimonial::MAX_DISPLAY_COUNT)->toBe(10);
    });

    it('has is_featured in fillable', function () {
        $testimonial = new Testimonial;
        expect($testimonial->getFillable())->toContain('is_featured');
    });

    it('isFeatured returns true when is_featured is true', function () {
        $testimonial = Testimonial::factory()->make(['is_featured' => true]);
        expect($testimonial->isFeatured())->toBeTrue();
    });

    it('isFeatured returns false when is_featured is false', function () {
        $testimonial = Testimonial::factory()->make(['is_featured' => false]);
        expect($testimonial->isFeatured())->toBeFalse();
    });

    it('has scopeFeatured method', function () {
        expect(method_exists(Testimonial::class, 'scopeFeatured'))->toBeTrue();
    });

    it('has getClientDisplayName method', function () {
        expect(method_exists(Testimonial::class, 'getClientDisplayName'))->toBeTrue();
    });
});

// --- TestimonialService display logic ---

describe('TestimonialService::getApprovedTestimonialsForDisplay', function () {
    it('has getApprovedTestimonialsForDisplay method', function () {
        expect(method_exists(TestimonialService::class, 'getApprovedTestimonialsForDisplay'))->toBeTrue();
    });

    it('returns empty state when no approved testimonials exist (BR-446)', function () {
        $service = new TestimonialService;
        $tenant = Tenant::factory()->create();

        // Create only pending testimonials — should not appear
        Testimonial::factory()->count(3)->create([
            'tenant_id' => $tenant->id,
            'status' => Testimonial::STATUS_PENDING,
        ]);

        $result = $service->getApprovedTestimonialsForDisplay($tenant);

        expect($result['hasTestimonials'])->toBeFalse()
            ->and($result['testimonials'])->toHaveCount(0)
            ->and($result['totalApproved'])->toBe(0)
            ->and($result['hasFeaturedSelection'])->toBeFalse();
    });

    it('returns up to 10 approved testimonials when <= 10 exist (BR-447)', function () {
        $service = new TestimonialService;
        $tenant = Tenant::factory()->create();

        Testimonial::factory()->count(5)->create([
            'tenant_id' => $tenant->id,
            'status' => Testimonial::STATUS_APPROVED,
        ]);

        $result = $service->getApprovedTestimonialsForDisplay($tenant);

        expect($result['hasTestimonials'])->toBeTrue()
            ->and($result['testimonials'])->toHaveCount(5)
            ->and($result['totalApproved'])->toBe(5)
            ->and($result['hasFeaturedSelection'])->toBeFalse();
    });

    it('caps display at 10 when more than 10 approved but no featured (BR-447, BR-448)', function () {
        $service = new TestimonialService;
        $tenant = Tenant::factory()->create();

        Testimonial::factory()->count(15)->create([
            'tenant_id' => $tenant->id,
            'status' => Testimonial::STATUS_APPROVED,
            'is_featured' => false,
        ]);

        $result = $service->getApprovedTestimonialsForDisplay($tenant);

        expect($result['hasTestimonials'])->toBeTrue()
            ->and($result['testimonials'])->toHaveCount(10)
            ->and($result['totalApproved'])->toBe(15)
            ->and($result['hasFeaturedSelection'])->toBeFalse();
    });

    it('returns only featured testimonials when > 10 approved and featured exist (BR-448)', function () {
        $service = new TestimonialService;
        $tenant = Tenant::factory()->create();

        // Create 12 approved, non-featured
        Testimonial::factory()->count(12)->create([
            'tenant_id' => $tenant->id,
            'status' => Testimonial::STATUS_APPROVED,
            'is_featured' => false,
        ]);

        // Create 3 featured
        Testimonial::factory()->count(3)->create([
            'tenant_id' => $tenant->id,
            'status' => Testimonial::STATUS_APPROVED,
            'is_featured' => true,
        ]);

        $result = $service->getApprovedTestimonialsForDisplay($tenant);

        expect($result['hasTestimonials'])->toBeTrue()
            ->and($result['testimonials'])->toHaveCount(3)
            ->and($result['totalApproved'])->toBe(15)
            ->and($result['hasFeaturedSelection'])->toBeTrue();

        // All returned testimonials must be featured
        foreach ($result['testimonials'] as $t) {
            expect($t->is_featured)->toBeTrue();
        }
    });

    it('is tenant-scoped and does not return other tenant testimonials (BR-453)', function () {
        $service = new TestimonialService;
        $tenant1 = Tenant::factory()->create();
        $tenant2 = Tenant::factory()->create();

        // tenant2 has approved testimonials
        Testimonial::factory()->count(3)->create([
            'tenant_id' => $tenant2->id,
            'status' => Testimonial::STATUS_APPROVED,
        ]);

        $result = $service->getApprovedTestimonialsForDisplay($tenant1);

        expect($result['hasTestimonials'])->toBeFalse()
            ->and($result['testimonials'])->toHaveCount(0);
    });

    it('returns result structure with required keys', function () {
        $service = new TestimonialService;
        $tenant = Tenant::factory()->create();

        $result = $service->getApprovedTestimonialsForDisplay($tenant);

        expect($result)->toHaveKeys(['testimonials', 'hasTestimonials', 'totalApproved', 'hasFeaturedSelection']);
    });
});

// --- TestimonialService::toggleFeatured ---

describe('TestimonialService::toggleFeatured (BR-448)', function () {
    it('has toggleFeatured method', function () {
        expect(method_exists(TestimonialService::class, 'toggleFeatured'))->toBeTrue();
    });

    it('toggles is_featured from false to true for approved testimonial', function () {
        $service = new TestimonialService;
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create();

        $testimonial = Testimonial::factory()->create([
            'tenant_id' => $tenant->id,
            'status' => Testimonial::STATUS_APPROVED,
            'is_featured' => false,
        ]);

        $result = $service->toggleFeatured($user, $testimonial);

        expect($result['success'])->toBeTrue()
            ->and($result['is_featured'])->toBeTrue()
            ->and($testimonial->fresh()->is_featured)->toBeTrue();
    });

    it('toggles is_featured from true to false for approved testimonial', function () {
        $service = new TestimonialService;
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create();

        $testimonial = Testimonial::factory()->create([
            'tenant_id' => $tenant->id,
            'status' => Testimonial::STATUS_APPROVED,
            'is_featured' => true,
        ]);

        $result = $service->toggleFeatured($user, $testimonial);

        expect($result['success'])->toBeTrue()
            ->and($result['is_featured'])->toBeFalse()
            ->and($testimonial->fresh()->is_featured)->toBeFalse();
    });

    it('returns failure for non-approved testimonial', function () {
        $service = new TestimonialService;
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create();

        $testimonial = Testimonial::factory()->create([
            'tenant_id' => $tenant->id,
            'status' => Testimonial::STATUS_PENDING,
            'is_featured' => false,
        ]);

        $result = $service->toggleFeatured($user, $testimonial);

        expect($result['success'])->toBeFalse()
            ->and($testimonial->fresh()->is_featured)->toBeFalse();
    });

    it('returns failure for rejected testimonial', function () {
        $service = new TestimonialService;
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create();

        $testimonial = Testimonial::factory()->create([
            'tenant_id' => $tenant->id,
            'status' => Testimonial::STATUS_REJECTED,
            'is_featured' => false,
        ]);

        $result = $service->toggleFeatured($user, $testimonial);

        expect($result['success'])->toBeFalse();
    });
});

// --- Database schema ---

describe('Testimonials table schema (F-182)', function () {
    it('has is_featured column', function () {
        expect(\Illuminate\Support\Facades\Schema::hasColumn('testimonials', 'is_featured'))->toBeTrue();
    });

    it('has approved_at column', function () {
        expect(\Illuminate\Support\Facades\Schema::hasColumn('testimonials', 'approved_at'))->toBeTrue();
    });
});
