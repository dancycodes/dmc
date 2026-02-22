<?php

namespace App\Models;

use App\Traits\LogsActivityTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * F-215: Cook Promo Code Creation
 *
 * Represents a promotional discount code scoped to a tenant.
 *
 * BR-533: Code is alphanumeric, 3-20 chars, stored uppercase.
 * BR-534: Code is unique within the tenant.
 * BR-535: Discount types: percentage or fixed.
 * BR-543: Tenant-scoped.
 * BR-544: Defaults to active status.
 * BR-546: All changes logged via Spatie Activitylog.
 */
class PromoCode extends Model
{
    /** @use HasFactory<\Database\Factories\PromoCodeFactory> */
    use HasFactory, LogsActivityTrait;

    /**
     * Discount type constants.
     *
     * BR-535: Two valid discount types.
     */
    public const TYPE_PERCENTAGE = 'percentage';

    public const TYPE_FIXED = 'fixed';

    /**
     * Valid discount types.
     *
     * @var array<string>
     */
    public const DISCOUNT_TYPES = [
        self::TYPE_PERCENTAGE,
        self::TYPE_FIXED,
    ];

    /**
     * Status constants.
     *
     * BR-544: New codes created as active.
     */
    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    /**
     * Valid statuses.
     *
     * @var array<string>
     */
    public const STATUSES = [
        self::STATUS_ACTIVE,
        self::STATUS_INACTIVE,
    ];

    /**
     * Validation constraint constants.
     *
     * BR-536: Percentage range 1–100.
     * BR-537: Fixed range 1–100,000.
     * BR-538: Minimum order 0–100,000.
     * BR-539: Max uses 0 = unlimited.
     * BR-540: Max uses per client 0 = unlimited.
     */
    public const MIN_PERCENTAGE = 1;

    public const MAX_PERCENTAGE = 100;

    public const MIN_FIXED = 1;

    public const MAX_FIXED = 100000;

    public const MIN_ORDER_AMOUNT = 0;

    public const MAX_ORDER_AMOUNT = 100000;

    public const MAX_TOTAL_USES = 100000;

    public const MAX_PER_CLIENT_USES = 100;

    /**
     * The table associated with the model.
     */
    protected $table = 'promo_codes';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'created_by',
        'code',
        'discount_type',
        'discount_value',
        'minimum_order_amount',
        'max_uses',
        'max_uses_per_client',
        'times_used',
        'starts_at',
        'ends_at',
        'status',
    ];

    /**
     * Get model casts.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'discount_value' => 'integer',
            'minimum_order_amount' => 'integer',
            'max_uses' => 'integer',
            'max_uses_per_client' => 'integer',
            'times_used' => 'integer',
            'starts_at' => 'date',
            'ends_at' => 'date',
        ];
    }

    /**
     * The tenant this promo code belongs to.
     *
     * BR-543: Promo codes are tenant-scoped.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * The user who created this promo code.
     *
     * BR-545: Only the cook can create promo codes.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * The usages of this promo code (for F-218).
     */
    public function usages(): HasMany
    {
        return $this->hasMany(PromoCodeUsage::class);
    }

    /**
     * Scope to only active promo codes.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<PromoCode>  $query
     * @return \Illuminate\Database\Eloquent\Builder<PromoCode>
     */
    public function scopeActive($query): mixed
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    /**
     * Scope to only the current tenant's promo codes.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<PromoCode>  $query
     * @return \Illuminate\Database\Eloquent\Builder<PromoCode>
     */
    public function scopeForTenant($query, int $tenantId): mixed
    {
        return $query->where('tenant_id', $tenantId);
    }

    /**
     * Get a human-readable label for the discount value.
     */
    public function getDiscountLabelAttribute(): string
    {
        if ($this->discount_type === self::TYPE_PERCENTAGE) {
            return $this->discount_value.'%';
        }

        return number_format($this->discount_value).' XAF';
    }

    /**
     * Get human-readable max uses label.
     */
    public function getMaxUsesLabelAttribute(): string
    {
        if ($this->max_uses === 0) {
            return '∞';
        }

        return (string) $this->max_uses;
    }

    /**
     * Get human-readable max uses per client label.
     */
    public function getMaxUsesPerClientLabelAttribute(): string
    {
        if ($this->max_uses_per_client === 0) {
            return '∞';
        }

        return (string) $this->max_uses_per_client;
    }

    /**
     * Whether this promo code is currently active (status + date check).
     */
    public function isCurrentlyActive(): bool
    {
        if ($this->status !== self::STATUS_ACTIVE) {
            return false;
        }

        $today = now()->toDateString();

        if ($this->starts_at->toDateString() > $today) {
            return false;
        }

        if ($this->ends_at !== null && $this->ends_at->toDateString() < $today) {
            return false;
        }

        return true;
    }

    /**
     * Whether a given ends_at date string has expired.
     *
     * BR-567: Expired status is computed: current date > end date.
     *
     * Static to avoid requiring an Eloquent DB context in unit tests.
     */
    public static function computeIsExpired(?string $endsAt): bool
    {
        if ($endsAt === null) {
            return false;
        }

        return $endsAt < now()->toDateString();
    }

    /**
     * Whether this promo code has expired (computed from end date).
     *
     * BR-567: Expired status is computed: current date > end date.
     */
    public function isExpired(): bool
    {
        return self::computeIsExpired(
            $this->ends_at !== null ? $this->ends_at->toDateString() : null
        );
    }
}
