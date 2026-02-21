<?php

namespace App\Models;

use App\Traits\LogsActivityTrait;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * F-151: Payment Webhook Handling
 *
 * Tracks wallet credits, commission deductions, refunds, and withdrawals.
 * BR-366: Cook wallet credited on successful payment.
 * BR-367: Credit initially unwithdrawable, becomes withdrawable after delay.
 * BR-368: Platform commission = order amount * cook's commission rate.
 * BR-369: Commission recorded as a separate transaction record.
 */
class WalletTransaction extends Model
{
    /** @use HasFactory<\Database\Factories\WalletTransactionFactory> */
    use HasFactory, LogsActivityTrait;

    /**
     * The table associated with the model.
     */
    protected $table = 'wallet_transactions';

    /**
     * Transaction type constants.
     */
    public const TYPE_PAYMENT_CREDIT = 'payment_credit';

    public const TYPE_COMMISSION = 'commission';

    public const TYPE_REFUND = 'refund';

    public const TYPE_WITHDRAWAL = 'withdrawal';

    public const TYPE_REFUND_DEDUCTION = 'refund_deduction';

    public const TYPE_WALLET_PAYMENT = 'wallet_payment';

    public const TYPE_BECAME_WITHDRAWABLE = 'became_withdrawable';

    /**
     * F-163: Cook wallet transaction type for order cancellation reversal.
     *
     * BR-253: Cook wallet transaction (type: order_cancelled, debit from unwithdrawable).
     */
    public const TYPE_ORDER_CANCELLED = 'order_cancelled';

    /**
     * Valid transaction types.
     *
     * @var array<string>
     */
    public const TYPES = [
        self::TYPE_PAYMENT_CREDIT,
        self::TYPE_COMMISSION,
        self::TYPE_REFUND,
        self::TYPE_WITHDRAWAL,
        self::TYPE_REFUND_DEDUCTION,
        self::TYPE_WALLET_PAYMENT,
        self::TYPE_BECAME_WITHDRAWABLE,
        self::TYPE_ORDER_CANCELLED,
    ];

    /**
     * Default withdrawable delay in hours.
     */
    public const DEFAULT_WITHDRAWABLE_DELAY_HOURS = 3;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'tenant_id',
        'order_id',
        'payment_transaction_id',
        'type',
        'amount',
        'currency',
        'balance_before',
        'balance_after',
        'is_withdrawable',
        'withdrawable_at',
        'status',
        'description',
        'metadata',
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
            'balance_before' => 'decimal:2',
            'balance_after' => 'decimal:2',
            'is_withdrawable' => 'boolean',
            'withdrawable_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    /**
     * Get the user (cook) who owns this wallet transaction.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the tenant associated with this transaction.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the order associated with this transaction.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Get the payment transaction associated with this wallet transaction.
     */
    public function paymentTransaction(): BelongsTo
    {
        return $this->belongsTo(PaymentTransaction::class);
    }

    /**
     * Get the formatted amount with currency.
     */
    public function formattedAmount(): string
    {
        return number_format((float) $this->amount, 0, '.', ',').' '.$this->currency;
    }

    /**
     * Check if this transaction is a credit (positive amount).
     */
    public function isCredit(): bool
    {
        return in_array($this->type, [self::TYPE_PAYMENT_CREDIT, self::TYPE_REFUND], true);
    }

    /**
     * Check if this transaction is a debit (negative amount).
     */
    public function isDebit(): bool
    {
        return in_array($this->type, [
            self::TYPE_COMMISSION,
            self::TYPE_WITHDRAWAL,
            self::TYPE_REFUND_DEDUCTION,
            self::TYPE_WALLET_PAYMENT,
            self::TYPE_ORDER_CANCELLED,
        ], true);
    }

    /**
     * Scope: filter by user.
     */
    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope: filter by type.
     */
    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    /**
     * Scope: filter by order.
     */
    public function scopeForOrder(Builder $query, int $orderId): Builder
    {
        return $query->where('order_id', $orderId);
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
