<?php

namespace App\Models;

use App\Traits\LogsActivityTrait;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * F-180: Testimonial Submission Form
 *
 * Represents a client testimonial submitted on a cook's tenant landing page.
 * BR-426: Only clients with completed orders can submit.
 * BR-427: One per client per tenant — enforced via unique DB constraint.
 * BR-429: Starts in 'pending' status; requires cook approval (F-181).
 * BR-430: Immutable after submission by the client.
 */
class Testimonial extends Model
{
    /** @use HasFactory<\Database\Factories\TestimonialFactory> */
    use HasFactory, LogsActivityTrait;

    /**
     * The table associated with the model.
     */
    protected $table = 'testimonials';

    /**
     * Status constants.
     */
    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    /**
     * All valid statuses.
     *
     * @var array<string>
     */
    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_APPROVED,
        self::STATUS_REJECTED,
    ];

    /**
     * Maximum testimonial text length in characters.
     *
     * BR-428: Maximum of 1,000 characters.
     */
    public const MAX_TEXT_LENGTH = 1000;

    /**
     * Maximum number of featured testimonials displayed on the landing page.
     *
     * BR-447: Maximum 10 testimonials displayed at a time.
     */
    public const MAX_DISPLAY_COUNT = 10;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'user_id',
        'text',
        'status',
        'is_featured',
        'approved_at',
        'rejected_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_featured' => 'boolean',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Get the tenant this testimonial belongs to.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the user (client) who submitted this testimonial.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope: filter by status.
     */
    public function scopeStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    /**
     * Scope: filter approved testimonials.
     */
    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    /**
     * Scope: filter pending testimonials.
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope: filter by tenant.
     */
    public function scopeForTenant(Builder $query, int $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }

    /**
     * Scope: filter featured testimonials.
     *
     * BR-448: Cook can mark approved testimonials as featured for display.
     */
    public function scopeFeatured(Builder $query): Builder
    {
        return $query->where('is_featured', true);
    }

    /**
     * Check whether this testimonial is approved.
     */
    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    /**
     * Check whether this testimonial is pending review.
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Check whether this testimonial is featured for landing page display.
     *
     * BR-448: Featured testimonials are shown when there are more than 10 approved.
     */
    public function isFeatured(): bool
    {
        return (bool) $this->is_featured;
    }

    /**
     * Get the client's display name: first name + last initial.
     *
     * BR-449: Each testimonial shows client name (first name + last initial).
     * Edge case: If user is deactivated, shows "Former User".
     */
    public function getClientDisplayName(): string
    {
        $user = $this->user;

        if (! $user) {
            return __('Former User');
        }

        $name = trim($user->name ?? '');

        if (empty($name)) {
            return __('Former User');
        }

        $parts = explode(' ', $name);

        if (count($parts) === 1) {
            return $parts[0];
        }

        $firstName = $parts[0];
        $lastInitial = mb_strtoupper(mb_substr(end($parts), 0, 1));

        return $firstName.' '.$lastInitial.'.';
    }

    /**
     * Get the status label for display.
     */
    public function getStatusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => __('Pending Review'),
            self::STATUS_APPROVED => __('Approved'),
            self::STATUS_REJECTED => __('Rejected'),
            default => __(ucfirst($this->status)),
        };
    }
}
