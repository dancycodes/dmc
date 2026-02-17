<?php

namespace App\Models;

use App\Traits\LogsActivityTrait;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayoutTask extends Model
{
    /** @use HasFactory<\Database\Factories\PayoutTaskFactory> */
    use HasFactory, LogsActivityTrait;

    /**
     * The table associated with the model.
     */
    protected $table = 'payout_tasks';

    /**
     * BR-199: Valid payout task statuses.
     */
    public const STATUS_PENDING = 'pending';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_MANUALLY_COMPLETED = 'manually_completed';

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_COMPLETED,
        self::STATUS_MANUALLY_COMPLETED,
    ];

    /**
     * BR-202: Maximum automatic retry attempts.
     */
    public const MAX_RETRIES = 3;

    /**
     * Valid payment methods (matches PaymentTransaction).
     */
    public const PAYMENT_METHODS = ['mtn_mobile_money', 'orange_money'];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'cook_id',
        'tenant_id',
        'amount',
        'currency',
        'mobile_money_number',
        'payment_method',
        'failure_reason',
        'flutterwave_reference',
        'flutterwave_transfer_id',
        'flutterwave_response',
        'status',
        'retry_count',
        'reference_number',
        'resolution_notes',
        'completed_by',
        'completed_at',
        'requested_at',
        'last_retry_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'flutterwave_response' => 'array',
            'retry_count' => 'integer',
            'completed_at' => 'datetime',
            'requested_at' => 'datetime',
            'last_retry_at' => 'datetime',
        ];
    }

    /**
     * Get the cook associated with this payout task.
     */
    public function cook(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cook_id');
    }

    /**
     * Get the tenant associated with this payout task.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the admin who completed this task.
     */
    public function completedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    /**
     * Check if this task is pending (still in the active queue).
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Check if this task has been resolved (completed or manually completed).
     */
    public function isResolved(): bool
    {
        return in_array($this->status, [self::STATUS_COMPLETED, self::STATUS_MANUALLY_COMPLETED], true);
    }

    /**
     * BR-202: Check if automatic retries are still available.
     */
    public function canRetry(): bool
    {
        return $this->isPending() && $this->retry_count < self::MAX_RETRIES;
    }

    /**
     * Get the formatted amount with currency.
     */
    public function formattedAmount(): string
    {
        return number_format((float) $this->amount, 0, '.', ',').' '.$this->currency;
    }

    /**
     * Get human-readable payment method label.
     */
    public function paymentMethodLabel(): string
    {
        return match ($this->payment_method) {
            'mtn_mobile_money' => 'MTN Mobile Money',
            'orange_money' => 'Orange Money',
            default => $this->payment_method ?? __('Unknown'),
        };
    }

    /**
     * Get human-readable status label.
     */
    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => __('Pending'),
            self::STATUS_COMPLETED => __('Completed'),
            self::STATUS_MANUALLY_COMPLETED => __('Manually Completed'),
            default => ucfirst($this->status),
        };
    }

    /**
     * Format the mobile money number for display.
     * Shows as "+237 6XX XXX XXX" format.
     */
    public function maskedPhone(): string
    {
        $phone = $this->mobile_money_number;
        if (strlen($phone) >= 9) {
            // Format: +237 6XX XXX XXX
            $cleaned = preg_replace('/\D/', '', $phone);
            if (strlen($cleaned) === 9) {
                return '+237 '.substr($cleaned, 0, 1).'XX XXX '.substr($cleaned, -3);
            }
            if (strlen($cleaned) === 12 && str_starts_with($cleaned, '237')) {
                return '+237 '.substr($cleaned, 3, 1).'XX XXX '.substr($cleaned, -3);
            }
        }

        return $phone;
    }

    /**
     * Scope: filter by pending status (active queue).
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope: filter by resolved status (completed tab).
     */
    public function scopeResolved(Builder $query): Builder
    {
        return $query->whereIn('status', [self::STATUS_COMPLETED, self::STATUS_MANUALLY_COMPLETED]);
    }

    /**
     * Scope: search by cook name or mobile money number.
     */
    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        if (! $search || trim($search) === '') {
            return $query;
        }

        $term = '%'.trim($search).'%';

        return $query->where(function (Builder $q) use ($term) {
            $q->where('mobile_money_number', 'ilike', $term)
                ->orWhere('failure_reason', 'ilike', $term)
                ->orWhere('reference_number', 'ilike', $term)
                ->orWhereHas('cook', function (Builder $cookQuery) use ($term) {
                    $cookQuery->where('name', 'ilike', $term)
                        ->orWhere('email', 'ilike', $term);
                });
        });
    }

    /**
     * Additional attributes excluded from activity logging.
     *
     * @return array<string>
     */
    public function getAdditionalExcludedAttributes(): array
    {
        return ['flutterwave_response'];
    }
}
