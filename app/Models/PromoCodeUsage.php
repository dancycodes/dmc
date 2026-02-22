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
     * The promo code that was used.
     */
    public function promoCode(): BelongsTo
    {
        return $this->belongsTo(PromoCode::class);
    }

    /**
     * The user who used the promo code.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
