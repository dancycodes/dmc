<?php

namespace App\Models;

use App\Traits\LogsActivityTrait;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * F-174: Cook Auto-Deduction for Refunds
 *
 * Represents a pending deduction against a cook's future earnings.
 * Created when a refund is issued after the cook has already withdrawn
 * the order's payment. Automatically settled from future order payments.
 *
 * BR-366: Created when refund is issued after withdrawal.
 * BR-367: Automatically applied against future order earnings.
 * BR-368: Applied before payment enters cook's wallet.
 * BR-369: Partial settlement if payment < deduction.
 * BR-370: Full deduction taken if payment > deduction.
 * BR-371: Multiple deductions settled FIFO (oldest first).
 * BR-373: Each deduction creates a wallet transaction (type: auto_deduction).
 * BR-374: References original order and refund reason.
 * BR-376: Logged via Spatie Activitylog.
 */
class PendingDeduction extends Model
{
    /** @use HasFactory<\Database\Factories\PendingDeductionFactory> */
    use HasFactory, LogsActivityTrait;

    protected $table = 'pending_deductions';

    /**
     * Source constants for deduction origin.
     */
    public const SOURCE_COMPLAINT_REFUND = 'complaint_refund';

    public const SOURCE_CANCELLATION_REFUND = 'cancellation_refund';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'cook_wallet_id',
        'tenant_id',
        'user_id',
        'order_id',
        'original_amount',
        'remaining_amount',
        'reason',
        'source',
        'metadata',
        'settled_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'original_amount' => 'decimal:2',
            'remaining_amount' => 'decimal:2',
            'metadata' => 'array',
            'settled_at' => 'datetime',
        ];
    }

    /**
     * Get the cook wallet this deduction belongs to.
     */
    public function cookWallet(): BelongsTo
    {
        return $this->belongsTo(CookWallet::class);
    }

    /**
     * Get the tenant associated with this deduction.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the user (cook) who owes this deduction.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the original order that caused this deduction.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Check if this deduction is fully settled.
     */
    public function isSettled(): bool
    {
        return $this->settled_at !== null;
    }

    /**
     * Check if this deduction is still pending (unsettled).
     */
    public function isPending(): bool
    {
        return $this->settled_at === null && (float) $this->remaining_amount > 0;
    }

    /**
     * Get the amount that has been settled so far.
     */
    public function settledAmount(): float
    {
        return round((float) $this->original_amount - (float) $this->remaining_amount, 2);
    }

    /**
     * Get the settlement progress as a percentage.
     */
    public function settlementProgress(): float
    {
        $original = (float) $this->original_amount;
        if ($original <= 0) {
            return 100.0;
        }

        return round(($this->settledAmount() / $original) * 100, 1);
    }

    /**
     * Get the formatted original amount with currency.
     */
    public function formattedOriginalAmount(): string
    {
        return number_format((float) $this->original_amount, 0, '.', ',').' XAF';
    }

    /**
     * Get the formatted remaining amount with currency.
     */
    public function formattedRemainingAmount(): string
    {
        return number_format((float) $this->remaining_amount, 0, '.', ',').' XAF';
    }

    /**
     * Scope: unsettled deductions only.
     */
    public function scopeUnsettled(Builder $query): Builder
    {
        return $query->whereNull('settled_at')
            ->where('remaining_amount', '>', 0);
    }

    /**
     * Scope: settled deductions only.
     */
    public function scopeSettled(Builder $query): Builder
    {
        return $query->whereNotNull('settled_at');
    }

    /**
     * Scope: filter by tenant.
     */
    public function scopeForTenant(Builder $query, int $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }

    /**
     * Scope: filter by user (cook).
     */
    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope: FIFO order (oldest first).
     *
     * BR-371: Multiple deductions settled in FIFO order.
     */
    public function scopeOldestFirst(Builder $query): Builder
    {
        return $query->orderBy('created_at', 'asc');
    }

    /**
     * Additional attributes excluded from activity logging.
     *
     * @return array<string>
     */
    public function getAdditionalExcludedAttributes(): array
    {
        return ['metadata'];
    }
}
