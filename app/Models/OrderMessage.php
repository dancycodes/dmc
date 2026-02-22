<?php

namespace App\Models;

use App\Traits\LogsActivityTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderMessage extends Model
{
    use HasFactory;
    use LogsActivityTrait;

    /**
     * Sender role constants for message thread.
     * BR-243: Each message displays sender role.
     */
    public const ROLE_CLIENT = 'client';

    public const ROLE_COOK = 'cook';

    public const ROLE_MANAGER = 'manager';

    /**
     * Readable labels for sender roles.
     */
    public const ROLE_LABELS = [
        self::ROLE_CLIENT => 'Client',
        self::ROLE_COOK => 'Cook',
        self::ROLE_MANAGER => 'Manager',
    ];

    /**
     * Number of messages to load per page (BR-241, BR-242).
     */
    public const PER_PAGE = 20;

    protected $fillable = [
        'order_id',
        'sender_id',
        'sender_role',
        'body',
    ];

    /**
     * The message belongs to an order.
     * BR-239: Each order has exactly one message thread.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * The message belongs to a sender user.
     * Edge case: Sender account may be deleted (sender_id nullable).
     */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    /**
     * Get the human-readable role label for this message.
     */
    public function getRoleLabelAttribute(): string
    {
        return __(self::ROLE_LABELS[$this->sender_role] ?? ucfirst($this->sender_role));
    }

    /**
     * Get the display name for the sender.
     * Edge case: Deleted accounts show "Deleted User".
     */
    public function getSenderNameAttribute(): string
    {
        return $this->sender?->name ?? __('Deleted User');
    }
}
