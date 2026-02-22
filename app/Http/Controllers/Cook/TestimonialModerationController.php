<?php

namespace App\Http\Controllers\Cook;

use App\Http\Controllers\Controller;
use App\Models\Testimonial;
use App\Services\TestimonialService;
use Illuminate\Http\Request;

/**
 * F-181: Cook Testimonial Moderation
 *
 * Allows the cook (or manager with manage-testimonials permission) to
 * approve, reject, or un-approve testimonials submitted by clients.
 *
 * BR-443: Only users with `manage-testimonials` permission can moderate.
 * BR-444: Testimonials are tenant-scoped.
 */
class TestimonialModerationController extends Controller
{
    public function __construct(
        private readonly TestimonialService $testimonialService,
    ) {}

    /**
     * Display the testimonial moderation page.
     *
     * BR-444: Only testimonials belonging to the current tenant are shown.
     */
    public function index(Request $request): mixed
    {
        $user = auth()->user();
        $tenant = tenant();

        // BR-443: Permission check
        if (! $user->can('can-manage-testimonials')) {
            abort(403);
        }

        $tab = $request->get('tab', Testimonial::STATUS_PENDING);

        $data = $this->testimonialService->getModerationData($tenant, $tab);

        if ($request->isGaleNavigate('testimonials')) {
            return gale()->fragment('cook.testimonials.index', 'testimonials-content', $data);
        }

        return gale()->view('cook.testimonials.index', $data, web: true);
    }

    /**
     * Approve a pending (or rejected) testimonial.
     *
     * BR-439: Cook can approve testimonials.
     * BR-442: Action is logged via Spatie Activitylog.
     */
    public function approve(Request $request, Testimonial $testimonial): mixed
    {
        $user = auth()->user();
        $tenant = tenant();

        // BR-443: Permission check
        if (! $user->can('can-manage-testimonials')) {
            abort(403);
        }

        // BR-444: Tenant scope check
        if ($testimonial->tenant_id !== $tenant->id) {
            abort(403);
        }

        $result = $this->testimonialService->approve($user, $testimonial);

        if (! $result['success']) {
            return gale()->dispatch('toast', [
                'type' => 'error',
                'message' => $result['message'],
            ]);
        }

        $tab = $request->state('tab', Testimonial::STATUS_PENDING);
        $data = $this->testimonialService->getModerationData($tenant, $tab);

        return gale()
            ->fragment('cook.testimonials.index', 'testimonials-content', $data)
            ->dispatch('toast', [
                'type' => 'success',
                'message' => $result['message'],
            ]);
    }

    /**
     * Reject a pending testimonial.
     *
     * BR-439: Cook can reject testimonials.
     * BR-442: Action is logged via Spatie Activitylog.
     */
    public function reject(Request $request, Testimonial $testimonial): mixed
    {
        $user = auth()->user();
        $tenant = tenant();

        // BR-443: Permission check
        if (! $user->can('can-manage-testimonials')) {
            abort(403);
        }

        // BR-444: Tenant scope check
        if ($testimonial->tenant_id !== $tenant->id) {
            abort(403);
        }

        $result = $this->testimonialService->reject($user, $testimonial);

        if (! $result['success']) {
            return gale()->dispatch('toast', [
                'type' => 'error',
                'message' => $result['message'],
            ]);
        }

        $tab = $request->state('tab', Testimonial::STATUS_PENDING);
        $data = $this->testimonialService->getModerationData($tenant, $tab);

        return gale()
            ->fragment('cook.testimonials.index', 'testimonials-content', $data)
            ->dispatch('toast', [
                'type' => 'success',
                'message' => $result['message'],
            ]);
    }

    /**
     * Un-approve (revoke) a previously approved testimonial.
     *
     * BR-441: Un-approving moves the testimonial to rejected status.
     * BR-438: Removes testimonial from public landing page display.
     * BR-442: Action is logged via Spatie Activitylog.
     */
    public function unapprove(Request $request, Testimonial $testimonial): mixed
    {
        $user = auth()->user();
        $tenant = tenant();

        // BR-443: Permission check
        if (! $user->can('can-manage-testimonials')) {
            abort(403);
        }

        // BR-444: Tenant scope check
        if ($testimonial->tenant_id !== $tenant->id) {
            abort(403);
        }

        $result = $this->testimonialService->unapprove($user, $testimonial);

        if (! $result['success']) {
            return gale()->dispatch('toast', [
                'type' => 'error',
                'message' => $result['message'],
            ]);
        }

        $tab = $request->state('tab', Testimonial::STATUS_APPROVED);
        $data = $this->testimonialService->getModerationData($tenant, $tab);

        return gale()
            ->fragment('cook.testimonials.index', 'testimonials-content', $data)
            ->dispatch('toast', [
                'type' => 'success',
                'message' => $result['message'],
            ]);
    }
}
