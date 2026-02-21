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

    public const STATUS_REFUNDED = 'refunded';

    /**
     * F-156/F-159: Valid status transitions map.
     *
     * Maps current status to the next valid status(es).
     * Delivery and pickup have different paths after 'ready'.
     *
     * @var array<string, array<string>>
     */
    public const STATUS_TRANSITIONS = [
        self::STATUS_PAID => [self::STATUS_CONFIRMED],
        self::STATUS_CONFIRMED => [self::STATUS_PREPARING],
        self::STATUS_PREPARING => [self::STATUS_READY],
        self::STATUS_READY => [self::STATUS_OUT_FOR_DELIVERY, self::STATUS_READY_FOR_PICKUP],
        self::STATUS_OUT_FOR_DELIVERY => [self::STATUS_DELIVERED],
        self::STATUS_READY_FOR_PICKUP => [self::STATUS_PICKED_UP],
        self::STATUS_DELIVERED => [self::STATUS_COMPLETED],
        self::STATUS_PICKED_UP => [self::STATUS_COMPLETED],
    ];

    /**
     * Terminal statuses where no further transitions are possible.
     *
     * @var array<string>
     */
    public const TERMINAL_STATUSES = [
        self::STATUS_COMPLETED,
        self::STATUS_CANCELLED,
        self::STATUS_REFUNDED,
    ];

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
        self::STATUS_REFUNDED,
    ];

    /**
     * F-159 BR-204: Statuses from which cancellation is allowed.
     *
     * Before cook starts preparing.
     *
     * @var array<string>
     */
    public const CANCELLABLE_STATUSES = [
        self::STATUS_PENDING_PAYMENT,
        self::STATUS_PAID,
        self::STATUS_CONFIRMED,
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
        'wallet_amount',
        'phone',
        'payment_provider',
        'payment_phone',
        'retry_count',
        'payment_retry_expires_at',
        'items_snapshot',
        'notes',
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
            'wallet_amount' => 'decimal:2',
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
     * Get the status transitions for this order.
     *
     * F-157: Status transition records for timeline tracking.
     */
    public function statusTransitions(): HasMany
    {
        return $this->hasMany(OrderStatusTransition::class);
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
     * F-155: Scope - search by order number or client name.
     *
     * BR-159: Case-insensitive and matches partial strings.
     */
    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        if (empty($search)) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($search) {
            $q->where('order_number', 'ilike', '%'.$search.'%')
                ->orWhereHas('client', function (Builder $clientQuery) use ($search) {
                    $clientQuery->where('name', 'ilike', '%'.$search.'%');
                });
        });
    }

    /**
     * F-155: Get items summary string from items_snapshot.
     *
     * BR-163: Truncated list of meal names with quantities.
     */
    public function getItemsSummaryAttribute(): string
    {
        return \App\Services\CookOrderService::getItemsSummary($this);
    }

    /**
     * F-156: Get the next valid status for this order.
     *
     * BR-172: The status update button shows the next valid status only.
     * For 'ready' status, the next status depends on delivery_method.
     *
     * @return string|null The next valid status, or null if terminal/no transition available.
     */
    public function getNextStatus(): ?string
    {
        if (in_array($this->status, self::TERMINAL_STATUSES, true)) {
            return null;
        }

        $transitions = self::STATUS_TRANSITIONS[$this->status] ?? [];

        if (empty($transitions)) {
            return null;
        }

        // For 'ready' status, pick based on delivery method
        if ($this->status === self::STATUS_READY) {
            return $this->delivery_method === self::METHOD_DELIVERY
                ? self::STATUS_OUT_FOR_DELIVERY
                : self::STATUS_READY_FOR_PICKUP;
        }

        return $transitions[0];
    }

    /**
     * F-156: Get a human-readable label for a given status.
     */
    public static function getStatusLabel(string $status): string
    {
        return match ($status) {
            self::STATUS_PENDING_PAYMENT => __('Pending Payment'),
            self::STATUS_PAID => __('Paid'),
            self::STATUS_CONFIRMED => __('Confirmed'),
            self::STATUS_PREPARING => __('Preparing'),
            self::STATUS_READY => __('Ready'),
            self::STATUS_OUT_FOR_DELIVERY => __('Out for Delivery'),
            self::STATUS_READY_FOR_PICKUP => __('Ready for Pickup'),
            self::STATUS_DELIVERED => __('Delivered'),
            self::STATUS_PICKED_UP => __('Picked Up'),
            self::STATUS_COMPLETED => __('Completed'),
            self::STATUS_CANCELLED => __('Cancelled'),
            self::STATUS_PAYMENT_FAILED => __('Payment Failed'),
            self::STATUS_REFUNDED => __('Refunded'),
            default => __(ucfirst(str_replace('_', ' ', $status))),
        };
    }

    /**
     * F-156: Check if the order is in a terminal state.
     */
    public function isTerminal(): bool
    {
        return in_array($this->status, self::TERMINAL_STATUSES, true);
    }

    /**
     * F-156: Get the payment provider label.
     *
     * BR-173: Payment information shows method label.
     */
    public function getPaymentProviderLabel(): string
    {
        return match ($this->payment_provider) {
            'mtn_momo' => 'MTN MoMo',
            'orange_money' => 'Orange Money',
            'wallet' => __('Wallet Balance'),
            'wallet_mtn_momo' => __('Wallet + MTN MoMo'),
            'wallet_orange_money' => __('Wallet + Orange Money'),
            default => $this->payment_provider ?? __('Unknown'),
        };
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
