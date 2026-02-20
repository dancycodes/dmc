<?php

namespace App\Models;

use App\Traits\LogsActivityTrait;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentTransaction extends Model
{
    /** @use HasFactory<\Database\Factories\PaymentTransactionFactory> */
    use HasFactory, LogsActivityTrait;

    /**
     * The table associated with the model.
     */
    protected $table = 'payment_transactions';

    /**
     * BR-151: Valid payment statuses.
     */
    public const STATUSES = ['pending', 'successful', 'failed', 'refunded'];

    /**
     * BR-153: Valid payment methods.
     */
    public const PAYMENT_METHODS = ['mtn_mobile_money', 'orange_money'];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'order_id',
        'client_id',
        'cook_id',
        'tenant_id',
        'amount',
        'currency',
        'payment_method',
        'status',
        'flutterwave_reference',
        'flutterwave_tx_ref',
        'flutterwave_fee',
        'settlement_amount',
        'payment_channel',
        'webhook_payload',
        'status_history',
        'response_code',
        'response_message',
        'refund_reason',
        'refund_amount',
        'customer_name',
        'customer_email',
        'customer_phone',
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
            'flutterwave_fee' => 'decimal:2',
            'settlement_amount' => 'decimal:2',
            'refund_amount' => 'decimal:2',
            'webhook_payload' => 'array',
            'status_history' => 'array',
        ];
    }

    /**
     * Get the order associated with this transaction.
     *
     * F-150: Links payment transactions to orders.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Get the client (customer) who made the payment.
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    /**
     * Get the cook who receives the payment.
     */
    public function cook(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cook_id');
    }

    /**
     * Get the tenant associated with this payment.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the formatted amount with currency.
     *
     * UI/UX: Amounts formatted as "15,000 XAF"
     */
    public function formattedAmount(): string
    {
        return number_format((float) $this->amount, 0, '.', ',').' '.$this->currency;
    }

    /**
     * Get the formatted refund amount with currency.
     */
    public function formattedRefundAmount(): string
    {
        if ($this->refund_amount === null) {
            return '0 '.$this->currency;
        }

        return number_format((float) $this->refund_amount, 0, '.', ',').' '.$this->currency;
    }

    /**
     * Get human-readable payment method label.
     *
     * BR-153: MTN Mobile Money, Orange Money
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
     * Check if this transaction has been pending for more than 15 minutes.
     *
     * Edge case: Payment in "pending" status for >15 minutes — row highlighted with warning indicator
     */
    public function isPendingTooLong(): bool
    {
        return $this->status === 'pending'
            && $this->created_at
            && $this->created_at->diffInMinutes(now()) > 15;
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
     * Scope: search by order ID, client name, client email, or Flutterwave reference.
     *
     * BR-155: Search covers order ID, client name, client email, Flutterwave transaction reference
     */
    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        if (! $search || trim($search) === '') {
            return $query;
        }

        $term = '%'.trim($search).'%';

        return $query->where(function (Builder $q) use ($term, $search) {
            $q->where('flutterwave_reference', 'ilike', $term)
                ->orWhere('flutterwave_tx_ref', 'ilike', $term)
                ->orWhere('customer_name', 'ilike', $term)
                ->orWhere('customer_email', 'ilike', $term);

            // Search by order_id if the search term is numeric
            if (is_numeric(trim($search))) {
                $q->orWhere('order_id', (int) trim($search));
            }

            // Search order ID patterns like "ORD-1042"
            if (preg_match('/^ORD-?(\d+)$/i', trim($search), $matches)) {
                $q->orWhere('order_id', (int) $matches[1]);
            }
        });
    }

    /**
     * Additional attributes excluded from activity logging.
     *
     * @return array<string>
     */
    public function getAdditionalExcludedAttributes(): array
    {
        return ['webhook_payload'];
    }
}
