<?php

namespace App\Models;

use App\Traits\LogsActivityTrait;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * F-172: Cook Withdrawal Request
 *
 * Represents a cook's request to withdraw funds from their wallet
 * to their mobile money account.
 *
 * BR-351: Created with status "pending" upon submission.
 * BR-352: Withdrawable balance decremented immediately.
 * BR-354: All withdrawal actions logged via Spatie Activitylog.
 */
class WithdrawalRequest extends Model
{
    /** @use HasFactory<\Database\Factories\WithdrawalRequestFactory> */
    use HasFactory, LogsActivityTrait;

    protected $table = 'withdrawal_requests';

    /**
     * Status constants.
     */
    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    /**
     * All valid statuses.
     *
     * @var list<string>
     */
    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_PROCESSING,
        self::STATUS_COMPLETED,
        self::STATUS_FAILED,
    ];

    /**
     * Mobile money provider constants.
     */
    public const PROVIDER_MTN_MOMO = 'mtn_momo';

    public const PROVIDER_ORANGE_MONEY = 'orange_money';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'cook_wallet_id',
        'tenant_id',
        'user_id',
        'amount',
        'currency',
        'mobile_money_number',
        'mobile_money_provider',
        'status',
        'flutterwave_reference',
        'failure_reason',
        'requested_at',
        'processed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'requested_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }

    /**
     * Get the cook wallet for this withdrawal.
     */
    public function cookWallet(): BelongsTo
    {
        return $this->belongsTo(CookWallet::class);
    }

    /**
     * Get the tenant for this withdrawal.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the user (cook) who made this withdrawal.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the formatted amount with currency.
     */
    public function formattedAmount(): string
    {
        return number_format((float) $this->amount, 0, '.', ',').' '.$this->currency;
    }

    /**
     * Get the provider label.
     */
    public function providerLabel(): string
    {
        return match ($this->mobile_money_provider) {
            self::PROVIDER_MTN_MOMO => 'MTN MoMo',
            self::PROVIDER_ORANGE_MONEY => 'Orange Money',
            default => __('Mobile Money'),
        };
    }

    /**
     * Check if this withdrawal is pending.
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Check if this withdrawal is completed.
     */
    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    /**
     * Check if this withdrawal has failed.
     */
    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    /**
     * Scope: filter by tenant.
     */
    public function scopeForTenant(Builder $query, int $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }

    /**
     * Scope: filter by user.
     */
    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope: filter by status.
     */
    public function scopeWithStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
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
