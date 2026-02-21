<?php

namespace App\Models;

use App\Traits\LogsActivityTrait;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * F-171: Withdrawable Timer Logic
 *
 * Tracks the clearance state of funds from completed orders.
 * BR-333: Hold period starts when order status changes to Completed.
 * BR-334: Default hold period is 3 hours, configurable via platform settings.
 * BR-335: After hold period expires, funds transition to withdrawable.
 * BR-338: Timer pauses if a complaint is filed during the hold period.
 * BR-339: Timer resumes when complaint is resolved (no refund).
 * BR-340: If complaint results in refund, funds are removed (never become withdrawable).
 * BR-341: Changes to hold period setting apply only to orders completed after the change.
 */
class OrderClearance extends Model
{
    /** @use HasFactory<\Database\Factories\OrderClearanceFactory> */
    use HasFactory, LogsActivityTrait;

    protected $table = 'order_clearances';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'order_id',
        'tenant_id',
        'cook_id',
        'complaint_id',
        'amount',
        'hold_hours',
        'completed_at',
        'withdrawable_at',
        'paused_at',
        'remaining_seconds_at_pause',
        'cleared_at',
        'is_cleared',
        'is_paused',
        'is_cancelled',
        'is_flagged_for_review',
        'blocked_at',
        'unblocked_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'hold_hours' => 'integer',
            'completed_at' => 'datetime',
            'withdrawable_at' => 'datetime',
            'paused_at' => 'datetime',
            'remaining_seconds_at_pause' => 'integer',
            'cleared_at' => 'datetime',
            'is_cleared' => 'boolean',
            'is_paused' => 'boolean',
            'is_cancelled' => 'boolean',
            'is_flagged_for_review' => 'boolean',
            'blocked_at' => 'datetime',
            'unblocked_at' => 'datetime',
        ];
    }

    /**
     * Get the order this clearance belongs to.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Get the tenant (cook's store) for this clearance.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the cook who will receive the funds.
     */
    public function cook(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cook_id');
    }

    /**
     * F-186: Get the complaint that triggered the payment block.
     */
    public function complaint(): BelongsTo
    {
        return $this->belongsTo(Complaint::class);
    }

    /**
     * F-186 BR-218: Check if this clearance is blocked due to a complaint.
     */
    public function isBlocked(): bool
    {
        return $this->is_paused && $this->complaint_id !== null && $this->blocked_at !== null;
    }

    /**
     * F-186 BR-223: Check if this clearance is flagged for review
     * (complaint filed after funds became withdrawable).
     */
    public function isFlaggedForReview(): bool
    {
        return $this->is_flagged_for_review && $this->complaint_id !== null;
    }

    /**
     * F-186: Check if this clearance has any active complaint block or flag.
     */
    public function hasActiveComplaintBlock(): bool
    {
        return $this->isBlocked() || $this->isFlaggedForReview();
    }

    /**
     * Check if this clearance is eligible for transitioning to withdrawable.
     *
     * BR-335: Must not be cleared, paused, or cancelled, and hold period must have expired.
     */
    public function isEligibleForClearance(): bool
    {
        if ($this->is_cleared || $this->is_paused || $this->is_cancelled) {
            return false;
        }

        return $this->withdrawable_at && now()->greaterThanOrEqualTo($this->withdrawable_at);
    }

    /**
     * Check if this clearance is still in the hold period (not yet eligible).
     */
    public function isInHoldPeriod(): bool
    {
        return ! $this->is_cleared
            && ! $this->is_cancelled
            && ! $this->is_paused
            && $this->withdrawable_at
            && now()->lessThan($this->withdrawable_at);
    }

    /**
     * Scope: eligible for clearance (not cleared, not paused, not cancelled, hold expired).
     *
     * BR-336: Used by the scheduled job to find funds ready to transition.
     */
    public function scopeEligibleForClearance(Builder $query): Builder
    {
        return $query
            ->where('is_cleared', false)
            ->where('is_paused', false)
            ->where('is_cancelled', false)
            ->where('withdrawable_at', '<=', now());
    }

    /**
     * Scope: currently paused clearances.
     */
    public function scopePaused(Builder $query): Builder
    {
        return $query->where('is_paused', true)->where('is_cancelled', false);
    }

    /**
     * Scope: for a specific tenant.
     */
    public function scopeForTenant(Builder $query, int $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }

    /**
     * Scope: for a specific order.
     */
    public function scopeForOrder(Builder $query, int $orderId): Builder
    {
        return $query->where('order_id', $orderId);
    }

    /**
     * F-186: Scope for blocked clearances (paused by complaint).
     */
    public function scopeBlocked(Builder $query): Builder
    {
        return $query->where('is_paused', true)
            ->whereNotNull('complaint_id')
            ->whereNotNull('blocked_at');
    }

    /**
     * F-186: Scope for flagged clearances (already withdrawable when complaint filed).
     */
    public function scopeFlaggedForReview(Builder $query): Builder
    {
        return $query->where('is_flagged_for_review', true)
            ->whereNotNull('complaint_id');
    }

    /**
     * F-186: Scope for all clearances with active complaint blocks or flags.
     */
    public function scopeWithActiveComplaintBlock(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            $q->where(function (Builder $sub) {
                $sub->where('is_paused', true)
                    ->whereNotNull('complaint_id')
                    ->whereNotNull('blocked_at');
            })->orWhere(function (Builder $sub) {
                $sub->where('is_flagged_for_review', true)
                    ->whereNotNull('complaint_id');
            });
        })->whereNull('unblocked_at');
    }

    /**
     * Additional attributes excluded from activity logging.
     *
     * @return array<string>
     */
    public function getAdditionalExcludedAttributes(): array
    {
        return [];
    }
}
