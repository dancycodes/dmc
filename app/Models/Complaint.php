<?php

namespace App\Models;

use App\Traits\LogsActivityTrait;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Complaint extends Model
{
    /** @use HasFactory<\Database\Factories\ComplaintFactory> */
    use HasFactory, LogsActivityTrait;

    /**
     * The table associated with the model.
     */
    protected $table = 'complaints';

    /**
     * BR-161: Complaint categories.
     */
    public const CATEGORIES = [
        'food_quality',
        'late_delivery',
        'missing_items',
        'wrong_order',
        'rude_behavior',
        'other',
    ];

    /**
     * BR-162: Complaint statuses in admin queue.
     */
    public const ADMIN_STATUSES = [
        'pending_resolution',
        'under_review',
        'resolved',
        'dismissed',
    ];

    /**
     * BR-165: Resolution types available to admins.
     */
    public const RESOLUTION_TYPES = [
        'dismiss',
        'partial_refund',
        'full_refund',
        'warning',
        'suspend',
    ];

    /**
     * All valid statuses including pre-escalation.
     */
    public const ALL_STATUSES = [
        'open',
        'responded',
        'escalated',
        'pending_resolution',
        'under_review',
        'resolved',
        'dismissed',
    ];

    /**
     * Escalation reason constants.
     */
    public const ESCALATION_AUTO_24H = 'auto_24h';

    public const ESCALATION_MANUAL_CLIENT = 'manual_client';

    public const ESCALATION_MANUAL_COOK = 'manual_cook';

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
        'category',
        'description',
        'status',
        'is_escalated',
        'escalation_reason',
        'escalated_at',
        'escalated_by',
        'resolved_by',
        'resolution_notes',
        'resolution_type',
        'refund_amount',
        'suspension_days',
        'suspension_ends_at',
        'resolved_at',
        'cook_response',
        'cook_responded_at',
        'submitted_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_escalated' => 'boolean',
            'escalated_at' => 'datetime',
            'resolved_at' => 'datetime',
            'cook_responded_at' => 'datetime',
            'submitted_at' => 'datetime',
            'suspension_ends_at' => 'datetime',
            'refund_amount' => 'decimal:2',
        ];
    }

    /**
     * Get the client who submitted the complaint.
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    /**
     * Get the cook the complaint is against.
     */
    public function cook(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cook_id');
    }

    /**
     * Get the tenant associated with this complaint.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the user who escalated this complaint.
     */
    public function escalatedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'escalated_by');
    }

    /**
     * Get the user who resolved this complaint.
     */
    public function resolvedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    /**
     * Get the human-readable category label.
     */
    public function categoryLabel(): string
    {
        return match ($this->category) {
            'food_quality' => __('Food Quality'),
            'late_delivery' => __('Late Delivery'),
            'missing_items' => __('Missing Items'),
            'wrong_order' => __('Wrong Order'),
            'rude_behavior' => __('Rude Behavior'),
            'other' => __('Other'),
            default => ucfirst(str_replace('_', ' ', $this->category)),
        };
    }

    /**
     * Get the human-readable status label.
     */
    public function statusLabel(): string
    {
        return match ($this->status) {
            'open' => __('Open'),
            'responded' => __('Responded'),
            'escalated' => __('Escalated'),
            'pending_resolution' => __('Pending Resolution'),
            'under_review' => __('Under Review'),
            'resolved' => __('Resolved'),
            'dismissed' => __('Dismissed'),
            default => ucfirst(str_replace('_', ' ', $this->status)),
        };
    }

    /**
     * Get the human-readable escalation reason.
     *
     * BR-158: Auto-escalated complaints show specific reason text
     */
    public function escalationReasonLabel(): string
    {
        return match ($this->escalation_reason) {
            self::ESCALATION_AUTO_24H => __('Auto-escalated (24h no response)'),
            self::ESCALATION_MANUAL_CLIENT => __('Escalated by client'),
            self::ESCALATION_MANUAL_COOK => __('Escalated by cook'),
            default => $this->escalation_reason ?? __('Unknown'),
        };
    }

    /**
     * Check if this complaint is unresolved (still needs admin action).
     */
    public function isUnresolved(): bool
    {
        return in_array($this->status, ['pending_resolution', 'under_review', 'escalated'], true);
    }

    /**
     * BR-174: Check if this complaint has been resolved or dismissed.
     */
    public function isResolved(): bool
    {
        return in_array($this->status, ['resolved', 'dismissed'], true);
    }

    /**
     * Get human-readable resolution type label.
     */
    public function resolutionTypeLabel(): string
    {
        return match ($this->resolution_type) {
            'dismiss' => __('Dismissed'),
            'partial_refund' => __('Partial Refund'),
            'full_refund' => __('Full Refund'),
            'warning' => __('Warning to Cook'),
            'suspend' => __('Cook Suspended'),
            default => __('Unknown'),
        };
    }

    /**
     * Check if escalation is older than 48 hours.
     *
     * UI/UX: Red color for complaints older than 48 hours since escalation
     */
    public function isOverdue(): bool
    {
        return $this->escalated_at
            && $this->isUnresolved()
            && $this->escalated_at->diffInHours(now()) > 48;
    }

    /**
     * Get the time since escalation in a human-readable format.
     */
    public function timeSinceEscalation(): string
    {
        if (! $this->escalated_at) {
            return '—';
        }

        return $this->escalated_at->diffForHumans();
    }

    /**
     * Scope: only escalated complaints (admin queue).
     *
     * BR-163: The escalation queue only shows complaints that have reached admin level
     */
    public function scopeEscalated(Builder $query): Builder
    {
        return $query->where('is_escalated', true);
    }

    /**
     * Scope: filter by category.
     */
    public function scopeOfCategory(Builder $query, ?string $category): Builder
    {
        if (! $category || ! in_array($category, self::CATEGORIES, true)) {
            return $query;
        }

        return $query->where('category', $category);
    }

    /**
     * Scope: filter by admin status.
     */
    public function scopeOfStatus(Builder $query, ?string $status): Builder
    {
        if (! $status || ! in_array($status, self::ADMIN_STATUSES, true)) {
            return $query;
        }

        return $query->where('status', $status);
    }

    /**
     * Scope: search by complaint ID, client name, cook name, or description.
     */
    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        if (! $search || trim($search) === '') {
            return $query;
        }

        $term = '%'.trim($search).'%';

        return $query->where(function (Builder $q) use ($term, $search) {
            $q->where('description', 'ilike', $term)
                ->orWhereHas('client', function (Builder $clientQuery) use ($term) {
                    $clientQuery->where('name', 'ilike', $term)
                        ->orWhere('email', 'ilike', $term);
                })
                ->orWhereHas('cook', function (Builder $cookQuery) use ($term) {
                    $cookQuery->where('name', 'ilike', $term);
                });

            // Search by complaint ID if numeric
            if (is_numeric(trim($search))) {
                $q->orWhere('id', (int) trim($search));
            }

            // Search by order ID patterns like "ORD-1042"
            if (preg_match('/^ORD-?(\d+)$/i', trim($search), $matches)) {
                $q->orWhere('order_id', (int) $matches[1]);
            }
        });
    }

    /**
     * Scope: priority sort — oldest unresolved first.
     *
     * BR-160: Default sort: oldest unresolved complaints first (priority queue)
     * BR-164: Resolved and dismissed complaints remain visible but sorted below unresolved ones
     */
    public function scopePrioritySort(Builder $query): Builder
    {
        return $query
            ->orderByRaw("CASE WHEN status IN ('pending_resolution', 'under_review', 'escalated') THEN 0 ELSE 1 END ASC")
            ->orderBy('escalated_at', 'asc');
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
