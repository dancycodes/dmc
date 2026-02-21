<?php

namespace App\Models;

use App\Traits\LogsActivityTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * F-184: Cook/Manager Complaint Response model.
 *
 * Stores responses from cooks/managers to client complaints.
 * BR-203: Multiple responses allowed; only the first changes complaint status.
 */
class ComplaintResponse extends Model
{
    /** @use HasFactory<\Database\Factories\ComplaintResponseFactory> */
    use HasFactory, LogsActivityTrait;

    /**
     * The table associated with the model.
     */
    protected $table = 'complaint_responses';

    /**
     * BR-197: Resolution options available to cook/manager.
     */
    public const RESOLUTION_APOLOGY_ONLY = 'apology_only';

    public const RESOLUTION_PARTIAL_REFUND = 'partial_refund_offer';

    public const RESOLUTION_FULL_REFUND = 'full_refund_offer';

    /**
     * All valid resolution types.
     *
     * @var list<string>
     */
    public const RESOLUTION_TYPES = [
        self::RESOLUTION_APOLOGY_ONLY,
        self::RESOLUTION_PARTIAL_REFUND,
        self::RESOLUTION_FULL_REFUND,
    ];

    /**
     * BR-196: Response constraints.
     */
    public const MIN_MESSAGE_LENGTH = 10;

    public const MAX_MESSAGE_LENGTH = 2000;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'complaint_id',
        'user_id',
        'message',
        'resolution_type',
        'refund_amount',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'refund_amount' => 'integer',
        ];
    }

    /**
     * Get the complaint this response belongs to.
     */
    public function complaint(): BelongsTo
    {
        return $this->belongsTo(Complaint::class);
    }

    /**
     * Get the user (cook/manager) who wrote this response.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get human-readable resolution type label.
     */
    public function resolutionTypeLabel(): string
    {
        return match ($this->resolution_type) {
            self::RESOLUTION_APOLOGY_ONLY => __('Apology Only'),
            self::RESOLUTION_PARTIAL_REFUND => __('Partial Refund Offer'),
            self::RESOLUTION_FULL_REFUND => __('Full Refund Offer'),
            default => __('No Resolution'),
        };
    }

    /**
     * Additional attributes excluded from activity logging.
     *
     * @return array<string>
     */
    public function getAdditionalExcludedAttributes(): array
    {
        return [];
    }
}
