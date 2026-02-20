<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * F-157: Order Status Transition record.
 *
 * Tracks each status change with previous/new status, timestamp,
 * and the user who triggered the transition.
 */
class OrderStatusTransition extends Model
{
    /** @use HasFactory<\Database\Factories\OrderStatusTransitionFactory> */
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'order_status_transitions';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'order_id',
        'triggered_by',
        'previous_status',
        'new_status',
    ];

    /**
     * Get the order this transition belongs to.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Get the user who triggered this transition.
     */
    public function triggeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by');
    }
}
