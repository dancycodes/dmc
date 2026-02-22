<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * F-218: Promo Code Application at Checkout (stub — created in F-215 for relationships)
 *
 * Records individual usage events of promo codes by clients at checkout.
 * The table will be created by the F-218 migration.
 */
class PromoCodeUsage extends Model
{
    /** @use HasFactory<\Database\Factories\PromoCodeUsageFactory> */
    use HasFactory;

    protected $table = 'promo_code_usages';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'promo_code_id',
        'order_id',
        'user_id',
        'discount_amount',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'discount_amount' => 'integer',
        ];
    }

    /**
     * The promo code that was used.
     */
    public function promoCode(): BelongsTo
    {
        return $this->belongsTo(PromoCode::class);
    }

    /**
     * The order on which this promo code was used.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * The user who used the promo code.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
