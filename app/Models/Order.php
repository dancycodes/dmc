<?php

namespace App\Models;

use App\Traits\LogsActivityTrait;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    /** @use HasFactory<\Database\Factories\OrderFactory> */
    use HasFactory, LogsActivityTrait;

    /**
     * The table associated with the model.
     */
    protected $table = 'orders';

    /**
     * Order status constants.
     */
    public const STATUS_PENDING_PAYMENT = 'pending_payment';

    public const STATUS_PAID = 'paid';

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_PREPARING = 'preparing';

    public const STATUS_READY = 'ready';

    public const STATUS_OUT_FOR_DELIVERY = 'out_for_delivery';

    public const STATUS_READY_FOR_PICKUP = 'ready_for_pickup';

    public const STATUS_DELIVERED = 'delivered';

    public const STATUS_PICKED_UP = 'picked_up';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_PAYMENT_FAILED = 'payment_failed';

    /**
     * Valid order statuses.
     *
     * @var array<string>
     */
    public const STATUSES = [
        self::STATUS_PENDING_PAYMENT,
        self::STATUS_PAID,
        self::STATUS_CONFIRMED,
        self::STATUS_PREPARING,
        self::STATUS_READY,
        self::STATUS_OUT_FOR_DELIVERY,
        self::STATUS_READY_FOR_PICKUP,
        self::STATUS_DELIVERED,
        self::STATUS_PICKED_UP,
        self::STATUS_COMPLETED,
        self::STATUS_CANCELLED,
        self::STATUS_PAYMENT_FAILED,
    ];

    /**
     * Delivery method constants.
     */
    public const METHOD_DELIVERY = 'delivery';

    public const METHOD_PICKUP = 'pickup';

    /**
     * Payment timeout in minutes.
     */
    public const PAYMENT_TIMEOUT_MINUTES = 15;

    /**
     * F-152: Maximum retry attempts allowed per order.
     *
     * BR-379: Maximum 3 retry attempts allowed per order.
     */
    public const MAX_RETRY_ATTEMPTS = 3;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'client_id',
        'tenant_id',
        'cook_id',
        'order_number',
        'status',
        'delivery_method',
        'town_id',
        'quarter_id',
        'neighbourhood',
        'pickup_location_id',
        'subtotal',
        'delivery_fee',
        'promo_discount',
        'grand_total',
        'phone',
        'payment_provider',
        'payment_phone',
        'retry_count',
        'payment_retry_expires_at',
        'items_snapshot',
        'paid_at',
        'confirmed_at',
        'completed_at',
        'cancelled_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'items_snapshot' => 'array',
            'retry_count' => 'integer',
            'payment_retry_expires_at' => 'datetime',
            'paid_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    /**
     * Get the client who placed the order.
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    /**
     * Get the tenant (cook's store) for this order.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the cook who receives the order.
     */
    public function cook(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cook_id');
    }

    /**
     * Get the town for delivery orders.
     */
    public function town(): BelongsTo
    {
        return $this->belongsTo(Town::class);
    }

    /**
     * Get the quarter for delivery orders.
     */
    public function quarter(): BelongsTo
    {
        return $this->belongsTo(Quarter::class);
    }

    /**
     * Get the pickup location for pickup orders.
     */
    public function pickupLocation(): BelongsTo
    {
        return $this->belongsTo(PickupLocation::class);
    }

    /**
     * Get the payment transactions for this order.
     */
    public function paymentTransactions(): HasMany
    {
        return $this->hasMany(PaymentTransaction::class);
    }

    /**
     * Get the wallet transactions for this order.
     *
     * F-151: Wallet credits and commission records linked to orders.
     */
    public function walletTransactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class);
    }

    /**
     * Generate a unique order number.
     *
     * Format: DMC-{YYMMDD}-{4 digit sequence}
     */
    public static function generateOrderNumber(): string
    {
        $prefix = 'DMC-'.now()->format('ymd').'-';
        $latestOrder = static::query()
            ->where('order_number', 'like', $prefix.'%')
            ->orderByDesc('id')
            ->first();

        if ($latestOrder) {
            $lastSequence = (int) substr($latestOrder->order_number, -4);
            $nextSequence = $lastSequence + 1;
        } else {
            $nextSequence = 1;
        }

        return $prefix.str_pad((string) $nextSequence, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Get the formatted grand total with currency.
     */
    public function formattedGrandTotal(): string
    {
        return number_format($this->grand_total, 0, '.', ',').' XAF';
    }

    /**
     * Check if this order is awaiting payment (pending payment status).
     *
     * BR-358: Order status is "Pending Payment" upon initiation.
     */
    public function isPendingPayment(): bool
    {
        return $this->status === self::STATUS_PENDING_PAYMENT;
    }

    /**
     * Check if payment has timed out (>15 minutes).
     *
     * BR-361: Timeout after 15 minutes if no webhook confirmation received.
     * F-152 BR-377: Uses payment_retry_expires_at if set.
     */
    public function isPaymentTimedOut(): bool
    {
        if ($this->payment_retry_expires_at) {
            return now()->greaterThan($this->payment_retry_expires_at);
        }

        return $this->isPendingPayment()
            && $this->created_at
            && $this->created_at->diffInMinutes(now()) > self::PAYMENT_TIMEOUT_MINUTES;
    }

    /**
     * Get remaining seconds until payment timeout.
     *
     * F-152 BR-377: Uses payment_retry_expires_at if set.
     */
    public function getPaymentTimeoutRemainingSeconds(): int
    {
        if ($this->payment_retry_expires_at) {
            $remaining = now()->diffInSeconds($this->payment_retry_expires_at, false);

            return max(0, (int) $remaining);
        }

        if (! $this->created_at) {
            return 0;
        }

        $elapsed = $this->created_at->diffInSeconds(now());
        $totalTimeout = self::PAYMENT_TIMEOUT_MINUTES * 60;
        $remaining = $totalTimeout - $elapsed;

        return max(0, (int) $remaining);
    }

    /**
     * F-152: Check if the order can be retried.
     *
     * BR-376: Order must be in pending_payment or payment_failed status.
     * BR-379: Must not exceed max retry attempts.
     * BR-377: Must be within the retry window.
     */
    public function canRetryPayment(): bool
    {
        if (! in_array($this->status, [self::STATUS_PENDING_PAYMENT, self::STATUS_PAYMENT_FAILED], true)) {
            return false;
        }

        if ($this->retry_count >= self::MAX_RETRY_ATTEMPTS) {
            return false;
        }

        if ($this->isPaymentTimedOut()) {
            return false;
        }

        return true;
    }

    /**
     * F-152: Check if max retries have been reached.
     *
     * BR-379: Maximum 3 retry attempts allowed per order.
     */
    public function hasExhaustedRetries(): bool
    {
        return $this->retry_count >= self::MAX_RETRY_ATTEMPTS;
    }

    /**
     * F-152: Get the retry window expiry time.
     *
     * BR-377: The retry window is 15 minutes from the initial payment attempt.
     */
    public function getRetryExpiresAt(): ?\Carbon\Carbon
    {
        return $this->payment_retry_expires_at;
    }

    /**
     * Scope: filter by status.
     */
    public function scopeStatus(Builder $query, ?string $status): Builder
    {
        if (! $status || ! in_array($status, self::STATUSES, true)) {
            return $query;
        }

        return $query->where('status', $status);
    }

    /**
     * Scope: filter by tenant.
     */
    public function scopeForTenant(Builder $query, int $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }

    /**
     * Scope: filter by client.
     */
    public function scopeForClient(Builder $query, int $clientId): Builder
    {
        return $query->where('client_id', $clientId);
    }

    /**
     * Additional attributes excluded from activity logging.
     *
     * @return array<string>
     */
    public function getAdditionalExcludedAttributes(): array
    {
        return ['items_snapshot'];
    }
}
