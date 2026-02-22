<?php

/**
 * F-181: Cook Testimonial Moderation — Unit Tests
 *
 * Tests the TestimonialService moderation methods (approve, reject, unapprove, getModerationData).
 * Uses factories and real DB interaction.
 */

use App\Models\Tenant;
use App\Models\Testimonial;
use App\Models\User;
use App\Services\TestimonialService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

$projectRoot = dirname(__DIR__, 3);

describe('TestimonialService moderation methods exist', function () use ($projectRoot) {
    it('has getModerationData method', function () {
        expect(method_exists(TestimonialService::class, 'getModerationData'))->toBeTrue();
    });

    it('has approve method', function () {
        expect(method_exists(TestimonialService::class, 'approve'))->toBeTrue();
    });

    it('has reject method', function () {
        expect(method_exists(TestimonialService::class, 'reject'))->toBeTrue();
    });

    it('has unapprove method', function () {
        expect(method_exists(TestimonialService::class, 'unapprove'))->toBeTrue();
    });

    it('controller file exists', function () use ($projectRoot) {
        $path = $projectRoot.'/app/Http/Controllers/Cook/TestimonialModerationController.php';
        expect(file_exists($path))->toBeTrue();
    });

    it('view file exists', function () use ($projectRoot) {
        $path = $projectRoot.'/resources/views/cook/testimonials/index.blade.php';
        expect(file_exists($path))->toBeTrue();
    });

    it('testimonials table has approved_at column', function () {
        expect(\Illuminate\Support\Facades\Schema::hasColumn('testimonials', 'approved_at'))->toBeTrue();
    });

    it('testimonials table has rejected_at column', function () {
        expect(\Illuminate\Support\Facades\Schema::hasColumn('testimonials', 'rejected_at'))->toBeTrue();
    });

    it('Testimonial model has approved_at and rejected_at in fillable', function () {
        $testimonial = new Testimonial;
        expect($testimonial->getFillable())
            ->toContain('approved_at')
            ->toContain('rejected_at');
    });
});

describe('TestimonialService::getModerationData', function () {
    it('returns counts grouped by status for the tenant', function () {
        $tenant = Tenant::factory()->create();

        Testimonial::factory()->count(2)->create([
            'tenant_id' => $tenant->id,
            'user_id' => fn () => User::factory()->create()->id,
            'status' => Testimonial::STATUS_PENDING,
        ]);
        Testimonial::factory()->create([
            'tenant_id' => $tenant->id,
            'user_id' => User::factory()->create()->id,
            'status' => Testimonial::STATUS_APPROVED,
        ]);
        Testimonial::factory()->create([
            'tenant_id' => $tenant->id,
            'user_id' => User::factory()->create()->id,
            'status' => Testimonial::STATUS_REJECTED,
        ]);

        $service = new TestimonialService;
        $data = $service->getModerationData($tenant, Testimonial::STATUS_PENDING);

        expect($data['counts']['pending'])->toBe(2)
            ->and($data['counts']['approved'])->toBe(1)
            ->and($data['counts']['rejected'])->toBe(1)
            ->and($data['activeTab'])->toBe(Testimonial::STATUS_PENDING);
    });

    it('returns testimonials only for the specified tab status', function () {
        $tenant = Tenant::factory()->create();

        Testimonial::factory()->create([
            'tenant_id' => $tenant->id,
            'user_id' => User::factory()->create()->id,
            'status' => Testimonial::STATUS_PENDING,
        ]);
        Testimonial::factory()->create([
            'tenant_id' => $tenant->id,
            'user_id' => User::factory()->create()->id,
            'status' => Testimonial::STATUS_APPROVED,
        ]);

        $service = new TestimonialService;
        $data = $service->getModerationData($tenant, Testimonial::STATUS_APPROVED);

        expect($data['testimonials']->total())->toBe(1)
            ->and($data['testimonials']->first()->status)->toBe(Testimonial::STATUS_APPROVED);
    });

    it('defaults to pending tab for invalid tab values', function () {
        $tenant = Tenant::factory()->create();
        $service = new TestimonialService;
        $data = $service->getModerationData($tenant, 'invalid-tab');

        expect($data['activeTab'])->toBe(Testimonial::STATUS_PENDING);
    });

    it('excludes testimonials from other tenants', function () {
        $tenant1 = Tenant::factory()->create();
        $tenant2 = Tenant::factory()->create();

        Testimonial::factory()->create([
            'tenant_id' => $tenant1->id,
            'user_id' => User::factory()->create()->id,
            'status' => Testimonial::STATUS_PENDING,
        ]);
        Testimonial::factory()->create([
            'tenant_id' => $tenant2->id,
            'user_id' => User::factory()->create()->id,
            'status' => Testimonial::STATUS_PENDING,
        ]);

        $service = new TestimonialService;
        $data = $service->getModerationData($tenant1, Testimonial::STATUS_PENDING);

        expect($data['testimonials']->total())->toBe(1)
            ->and($data['counts']['pending'])->toBe(1);
    });
});

describe('TestimonialService::approve', function () {
    it('approves a pending testimonial and sets approved_at', function () {
        $moderator = User::factory()->create();
        $testimonial = Testimonial::factory()->pending()->create([
            'user_id' => User::factory()->create()->id,
        ]);

        $service = new TestimonialService;
        $result = $service->approve($moderator, $testimonial);

        expect($result['success'])->toBeTrue();

        $testimonial->refresh();
        expect($testimonial->status)->toBe(Testimonial::STATUS_APPROVED)
            ->and($testimonial->approved_at)->not->toBeNull()
            ->and($testimonial->rejected_at)->toBeNull();
    });

    it('approves a rejected testimonial and clears rejected_at', function () {
        $moderator = User::factory()->create();
        $testimonial = Testimonial::factory()->rejected()->create([
            'user_id' => User::factory()->create()->id,
            'rejected_at' => now()->subDay(),
        ]);

        $service = new TestimonialService;
        $result = $service->approve($moderator, $testimonial);

        expect($result['success'])->toBeTrue();

        $testimonial->refresh();
        expect($testimonial->status)->toBe(Testimonial::STATUS_APPROVED)
            ->and($testimonial->approved_at)->not->toBeNull()
            ->and($testimonial->rejected_at)->toBeNull();
    });

    it('returns error when testimonial is already approved', function () {
        $moderator = User::factory()->create();
        $testimonial = Testimonial::factory()->approved()->create([
            'user_id' => User::factory()->create()->id,
            'approved_at' => now(),
        ]);

        $service = new TestimonialService;
        $result = $service->approve($moderator, $testimonial);

        expect($result['success'])->toBeFalse()
            ->and($result['message'])->not->toBeEmpty();
    });
});

describe('TestimonialService::reject', function () {
    it('rejects a pending testimonial and sets rejected_at', function () {
        $moderator = User::factory()->create();
        $testimonial = Testimonial::factory()->pending()->create([
            'user_id' => User::factory()->create()->id,
        ]);

        $service = new TestimonialService;
        $result = $service->reject($moderator, $testimonial);

        expect($result['success'])->toBeTrue();

        $testimonial->refresh();
        expect($testimonial->status)->toBe(Testimonial::STATUS_REJECTED)
            ->and($testimonial->rejected_at)->not->toBeNull()
            ->and($testimonial->approved_at)->toBeNull();
    });

    it('rejects an approved testimonial and clears approved_at', function () {
        $moderator = User::factory()->create();
        $testimonial = Testimonial::factory()->approved()->create([
            'user_id' => User::factory()->create()->id,
            'approved_at' => now()->subDay(),
        ]);

        $service = new TestimonialService;
        $result = $service->reject($moderator, $testimonial);

        expect($result['success'])->toBeTrue();

        $testimonial->refresh();
        expect($testimonial->status)->toBe(Testimonial::STATUS_REJECTED)
            ->and($testimonial->rejected_at)->not->toBeNull()
            ->and($testimonial->approved_at)->toBeNull();
    });

    it('returns error when testimonial is already rejected', function () {
        $moderator = User::factory()->create();
        $testimonial = Testimonial::factory()->rejected()->create([
            'user_id' => User::factory()->create()->id,
            'rejected_at' => now(),
        ]);

        $service = new TestimonialService;
        $result = $service->reject($moderator, $testimonial);

        expect($result['success'])->toBeFalse()
            ->and($result['message'])->not->toBeEmpty();
    });
});

describe('TestimonialService::unapprove', function () {
    it('un-approves an approved testimonial and moves it to rejected', function () {
        $moderator = User::factory()->create();
        $testimonial = Testimonial::factory()->approved()->create([
            'user_id' => User::factory()->create()->id,
            'approved_at' => now()->subDay(),
        ]);

        $service = new TestimonialService;
        $result = $service->unapprove($moderator, $testimonial);

        expect($result['success'])->toBeTrue();

        $testimonial->refresh();
        expect($testimonial->status)->toBe(Testimonial::STATUS_REJECTED)
            ->and($testimonial->rejected_at)->not->toBeNull()
            ->and($testimonial->approved_at)->toBeNull();
    });

    it('returns error when trying to unapprove a pending testimonial', function () {
        $moderator = User::factory()->create();
        $testimonial = Testimonial::factory()->pending()->create([
            'user_id' => User::factory()->create()->id,
        ]);

        $service = new TestimonialService;
        $result = $service->unapprove($moderator, $testimonial);

        expect($result['success'])->toBeFalse()
            ->and($result['message'])->not->toBeEmpty();
    });

    it('returns error when trying to unapprove an already rejected testimonial', function () {
        $moderator = User::factory()->create();
        $testimonial = Testimonial::factory()->rejected()->create([
            'user_id' => User::factory()->create()->id,
            'rejected_at' => now(),
        ]);

        $service = new TestimonialService;
        $result = $service->unapprove($moderator, $testimonial);

        expect($result['success'])->toBeFalse();
    });
});
