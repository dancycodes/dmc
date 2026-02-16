<?php

namespace App\Models;

use App\Traits\LogsActivityTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Address extends Model
{
    use HasFactory, LogsActivityTrait;

    /**
     * The table associated with the model.
     */
    protected $table = 'addresses';

    /**
     * Maximum number of saved addresses per user (BR-119).
     */
    public const MAX_ADDRESSES_PER_USER = 5;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'label',
        'town_id',
        'quarter_id',
        'neighbourhood',
        'additional_directions',
        'is_default',
        'latitude',
        'longitude',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
        ];
    }

    /**
     * Get the user that owns this address.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the town associated with this address.
     */
    public function town(): BelongsTo
    {
        return $this->belongsTo(Town::class);
    }

    /**
     * Get the quarter associated with this address.
     */
    public function quarter(): BelongsTo
    {
        return $this->belongsTo(Quarter::class);
    }

    /**
     * Check if this address has pending orders that reference it.
     *
     * Orders store address data at creation time (BR-146), so this only
     * matters when the user has a single address. Returns false until
     * the orders system is implemented (F-150+).
     */
    public function hasPendingOrders(): bool
    {
        // Orders table does not exist yet — will be implemented in F-150+.
        // When orders are added, this method should query:
        //   Order::where('address_id', $this->id)
        //       ->whereIn('status', ['pending_payment', 'paid', 'confirmed', 'preparing', 'ready', 'out_for_delivery'])
        //       ->exists();
        return false;
    }
}
