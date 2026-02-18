<?php

namespace App\Models;

use App\Traits\LogsActivityTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * F-101: Create Schedule Template
 * F-102: Schedule Template List View
 *
 * Represents a reusable schedule template with pre-configured time intervals
 * for order, delivery, and pickup. Templates save cooks time by bundling
 * a complete set of time intervals that can be applied to multiple days at
 * once (via F-105). Templates are tenant-scoped and independent — they are
 * copied to schedules when applied, not linked.
 */
class ScheduleTemplate extends Model
{
    /** @use HasFactory<\Database\Factories\ScheduleTemplateFactory> */
    use HasFactory, LogsActivityTrait;

    /**
     * The table associated with the model.
     */
    protected $table = 'schedule_templates';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'name',
        'order_start_time',
        'order_start_day_offset',
        'order_end_time',
        'order_end_day_offset',
        'delivery_enabled',
        'delivery_start_time',
        'delivery_end_time',
        'pickup_enabled',
        'pickup_start_time',
        'pickup_end_time',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'order_start_day_offset' => 'integer',
            'order_end_day_offset' => 'integer',
            'delivery_enabled' => 'boolean',
            'pickup_enabled' => 'boolean',
        ];
    }

    /**
     * Get the tenant that owns this template.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * F-102: Get the cook schedules that were created from this template.
     *
     * BR-137: The "applied to" count reflects how many schedule entries
     * were created from this template (tracked via template_id reference).
     */
    public function cookSchedules(): HasMany
    {
        return $this->hasMany(CookSchedule::class, 'template_id');
    }

    /**
     * Scope to filter by tenant.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeForTenant($query, int $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    /**
     * Get a human-readable summary of the order interval.
     */
    public function getOrderIntervalSummaryAttribute(): string
    {
        $startTime = $this->formatTime($this->order_start_time);
        $endTime = $this->formatTime($this->order_end_time);
        $startOffset = CookSchedule::formatDayOffset($this->order_start_day_offset);
        $endOffset = CookSchedule::formatDayOffset($this->order_end_day_offset);

        return __(':startTime :startOffset to :endTime :endOffset', [
            'startTime' => $startTime,
            'startOffset' => $startOffset,
            'endTime' => $endTime,
            'endOffset' => $endOffset,
        ]);
    }

    /**
     * Get a human-readable summary of the delivery interval.
     */
    public function getDeliveryIntervalSummaryAttribute(): ?string
    {
        if (! $this->delivery_enabled || ! $this->delivery_start_time || ! $this->delivery_end_time) {
            return null;
        }

        $startTime = $this->formatTime($this->delivery_start_time);
        $endTime = $this->formatTime($this->delivery_end_time);

        return __(':startTime to :endTime', [
            'startTime' => $startTime,
            'endTime' => $endTime,
        ]);
    }

    /**
     * Get a human-readable summary of the pickup interval.
     */
    public function getPickupIntervalSummaryAttribute(): ?string
    {
        if (! $this->pickup_enabled || ! $this->pickup_start_time || ! $this->pickup_end_time) {
            return null;
        }

        $startTime = $this->formatTime($this->pickup_start_time);
        $endTime = $this->formatTime($this->pickup_end_time);

        return __(':startTime to :endTime', [
            'startTime' => $startTime,
            'endTime' => $endTime,
        ]);
    }

    /**
     * Get the order interval end time in minutes from midnight.
     *
     * Reuses same logic as CookSchedule for consistency.
     * If order ends before the open day (offset > 0), returns 0.
     */
    public function getOrderEndTimeInMinutes(): int
    {
        if ($this->order_end_day_offset > 0) {
            return 0;
        }

        $parts = explode(':', $this->order_end_time);

        return ((int) $parts[0] * 60) + (int) ($parts[1] ?? 0);
    }

    /**
     * Format a time value for display (12-hour format with AM/PM).
     */
    private function formatTime(string $time): string
    {
        return date('g:i A', strtotime($time));
    }
}
