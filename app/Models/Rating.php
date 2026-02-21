<?php

namespace App\Models;

use App\Traits\LogsActivityTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * F-176: Order Rating
 *
 * Each completed order can be rated exactly once by the client.
 * Rating is 1-5 stars (integer only). Once submitted, it cannot
 * be edited or deleted by the client (BR-391).
 *
 * @property int $id
 * @property int $order_id
 * @property int $user_id
 * @property int $tenant_id
 * @property int $stars
 * @property string|null $review
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class Rating extends Model
{
    /** @use HasFactory<\Database\Factories\RatingFactory> */
    use HasFactory, LogsActivityTrait;

    /**
     * The table associated with the model.
     */
    protected $table = 'ratings';

    /**
     * Rating scale boundaries.
     */
    public const MIN_STARS = 1;

    public const MAX_STARS = 5;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'order_id',
        'user_id',
        'tenant_id',
        'stars',
        'review',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'stars' => 'integer',
        ];
    }

    /**
     * Get the order this rating belongs to.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Get the client who submitted the rating.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the tenant (cook's store) this rating is for.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
