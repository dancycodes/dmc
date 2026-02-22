<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Tenant;
use App\Models\Testimonial;
use App\Models\User;
use App\Notifications\NewTestimonialNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * F-180: Testimonial Submission Service
 *
 * Handles all business logic for testimonial submissions including
 * eligibility checking, duplicate detection, and submission processing.
 */
class TestimonialService
{
    /**
     * Check whether a user is eligible to submit a testimonial for a tenant.
     *
     * BR-426: Only authenticated clients who have completed at least 1 order
     * with this cook can submit a testimonial.
     */
    public function isEligible(User $user, Tenant $tenant): bool
    {
        return Order::query()
            ->where('client_id', $user->id)
            ->where('tenant_id', $tenant->id)
            ->where('status', Order::STATUS_COMPLETED)
            ->exists();
    }

    /**
     * Check whether a user has already submitted a testimonial for a tenant.
     *
     * BR-427: Each client can submit one testimonial per cook (per tenant).
     */
    public function hasExistingTestimonial(User $user, Tenant $tenant): bool
    {
        return Testimonial::query()
            ->where('user_id', $user->id)
            ->where('tenant_id', $tenant->id)
            ->exists();
    }

    /**
     * Get the existing testimonial for a user + tenant, or null.
     */
    public function getExistingTestimonial(User $user, Tenant $tenant): ?Testimonial
    {
        return Testimonial::query()
            ->where('user_id', $user->id)
            ->where('tenant_id', $tenant->id)
            ->first();
    }

    /**
     * Retrieve the submission context for the current user + tenant.
     *
     * Returns an array with:
     *   - isAuthenticated (bool)
     *   - isEligible (bool): has completed orders
     *   - existingTestimonial (Testimonial|null)
     *
     * @return array{isAuthenticated: bool, isEligible: bool, existingTestimonial: \App\Models\Testimonial|null}
     */
    public function getSubmissionContext(?User $user, Tenant $tenant): array
    {
        if (! $user) {
            return [
                'isAuthenticated' => false,
                'isEligible' => false,
                'existingTestimonial' => null,
            ];
        }

        $existingTestimonial = $this->getExistingTestimonial($user, $tenant);

        return [
            'isAuthenticated' => true,
            'isEligible' => $this->isEligible($user, $tenant),
            'existingTestimonial' => $existingTestimonial,
        ];
    }

    /**
     * Submit a new testimonial.
     *
     * BR-427: Returns the existing testimonial if one already exists (race-condition safety).
     * BR-429: Status starts as 'pending'.
     * BR-434: Notifies the cook (push + DB).
     * BR-435: Logs via Spatie Activitylog.
     *
     * @return array{success: bool, testimonial: \App\Models\Testimonial|null, message: string}
     */
    public function submit(User $user, Tenant $tenant, string $text): array
    {
        // BR-426: Verify eligibility
        if (! $this->isEligible($user, $tenant)) {
            return [
                'success' => false,
                'testimonial' => null,
                'message' => __('You need to complete an order before sharing your experience.'),
            ];
        }

        // BR-427: Check for existing testimonial (concurrent-submission safety)
        $existing = $this->getExistingTestimonial($user, $tenant);
        if ($existing) {
            return [
                'success' => false,
                'testimonial' => $existing,
                'message' => __("You've already shared your experience with this cook."),
            ];
        }

        $testimonial = DB::transaction(function () use ($user, $tenant, $text) {
            // BR-429: Create with pending status
            $testimonial = Testimonial::query()->create([
                'tenant_id' => $tenant->id,
                'user_id' => $user->id,
                'text' => trim($text),
                'status' => Testimonial::STATUS_PENDING,
            ]);

            // BR-435: Log the submission
            activity()
                ->causedBy($user)
                ->performedOn($testimonial)
                ->withProperties([
                    'tenant_id' => $tenant->id,
                    'tenant_name' => $tenant->name,
                    'text_length' => mb_strlen(trim($text)),
                ])
                ->log('testimonial_submitted');

            return $testimonial;
        });

        // BR-434: Notify the cook (push + DB) — outside transaction for non-critical delivery
        $this->notifyCook($tenant, $testimonial);

        return [
            'success' => true,
            'testimonial' => $testimonial,
            'message' => __('Thank you! Your testimonial has been submitted for review.'),
        ];
    }

    /**
     * Notify the cook that a new testimonial has been submitted.
     *
     * BR-434: Cook receives push + DB notification (N-018).
     */
    private function notifyCook(Tenant $tenant, Testimonial $testimonial): void
    {
        try {
            $cook = $tenant->cook;
            if ($cook) {
                $cook->notify(new NewTestimonialNotification($testimonial, $tenant));
            }
        } catch (\Throwable $e) {
            Log::warning('Failed to send new testimonial notification', [
                'testimonial_id' => $testimonial->id,
                'tenant_id' => $tenant->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
