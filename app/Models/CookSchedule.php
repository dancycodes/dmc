<?php

namespace App\Models;

use App\Traits\LogsActivityTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * F-098: Cook Day Schedule Creation
 * F-099: Order Time Interval Configuration
 * F-100: Delivery/Pickup Time Interval Configuration
 *
 * Represents a schedule entry for a specific day of the week within a tenant.
 * Multiple entries can exist per day (e.g., Lunch slot, Dinner slot) up to
 * a configurable maximum (default 3). Each entry can be marked as available
 * or unavailable and serves as the foundation for time interval configuration.
 *
 * Order interval fields define when clients can place orders relative to the
 * schedule's open day (order_start_time, order_start_day_offset,
 * order_end_time, order_end_day_offset).
 *
 * Delivery/pickup interval fields define when delivery and pickup are available
 * on the open day. Both must start at or after the order interval end time.
 */
class CookSchedule extends Model
{
    /** @use HasFactory<\Database\Factories\CookScheduleFactory> */
    use HasFactory, LogsActivityTrait;

    /**
     * The table associated with the model.
     */
    protected $table = 'cook_schedules';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'day_of_week',
        'is_available',
        'label',
        'position',
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
     * BR-100: Maximum schedule entries per day (configurable, default 3).
     */
    public const MAX_ENTRIES_PER_DAY = 3;

    /**
     * BR-110: Maximum day offset for order start (7 days before the open day).
     */
    public const MAX_START_DAY_OFFSET = 7;

    /**
     * BR-111: Maximum day offset for order end (1 day before the open day).
     */
    public const MAX_END_DAY_OFFSET = 1;

    /**
     * Valid days of the week.
     *
     * @var list<string>
     */
    public const DAYS_OF_WEEK = [
        'monday',
        'tuesday',
        'wednesday',
        'thursday',
        'friday',
        'saturday',
        'sunday',
    ];

    /**
     * Day labels for display (translatable).
     *
     * @var array<string, string>
     */
    public const DAY_LABELS = [
        'monday' => 'Monday',
        'tuesday' => 'Tuesday',
        'wednesday' => 'Wednesday',
        'thursday' => 'Thursday',
        'friday' => 'Friday',
        'saturday' => 'Saturday',
        'sunday' => 'Sunday',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_available' => 'boolean',
            'position' => 'integer',
            'order_start_day_offset' => 'integer',
            'order_end_day_offset' => 'integer',
            'delivery_enabled' => 'boolean',
            'pickup_enabled' => 'boolean',
        ];
    }

    /**
     * Get the tenant that owns this schedule entry.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the human-readable day label (translatable).
     */
    public function getDayLabelAttribute(): string
    {
        return __(self::DAY_LABELS[$this->day_of_week] ?? 'Unknown');
    }

    /**
     * Get the display label for this schedule entry.
     * BR-105: Defaults to "Slot N" based on position when label is empty.
     */
    public function getDisplayLabelAttribute(): string
    {
        if (! empty($this->label)) {
            return $this->label;
        }

        return __('Slot').' '.$this->position;
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
     * Scope to filter by day of week.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeForDay($query, string $day)
    {
        return $query->where('day_of_week', $day);
    }

    /**
     * Scope to filter available entries only.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeAvailable($query)
    {
        return $query->where('is_available', true);
    }

    /**
     * Check if this schedule entry has an order interval configured.
     */
    public function hasOrderInterval(): bool
    {
        return $this->order_start_time !== null && $this->order_end_time !== null;
    }

    /**
     * Get a human-readable summary of the order interval.
     *
     * Examples:
     * - "6:00 PM day before to 8:00 AM same day"
     * - "12:00 PM 2 days before to 6:00 PM day before"
     * - "6:00 AM same day to 10:00 AM same day"
     */
    public function getOrderIntervalSummaryAttribute(): ?string
    {
        if (! $this->hasOrderInterval()) {
            return null;
        }

        $startTime = $this->formatTime($this->order_start_time);
        $endTime = $this->formatTime($this->order_end_time);
        $startOffset = $this->formatDayOffset($this->order_start_day_offset);
        $endOffset = $this->formatDayOffset($this->order_end_day_offset);

        return __(':startTime :startOffset to :endTime :endOffset', [
            'startTime' => $startTime,
            'startOffset' => $startOffset,
            'endTime' => $endTime,
            'endOffset' => $endOffset,
        ]);
    }

    /**
     * Format a time value for display (12-hour format with AM/PM).
     */
    private function formatTime(string $time): string
    {
        return date('g:i A', strtotime($time));
    }

    /**
     * Format a day offset for display.
     *
     * @return string Human-readable day offset label
     */
    public static function formatDayOffset(int $offset): string
    {
        if ($offset === 0) {
            return __('same day');
        }

        if ($offset === 1) {
            return __('day before');
        }

        return __(':count days before', ['count' => $offset]);
    }

    /**
     * Get the day offset options for the start time.
     *
     * BR-106/BR-110: 0 (same day) through 7 (7 days before)
     *
     * @return array<int, string>
     */
    public static function getStartDayOffsetOptions(): array
    {
        $options = [];
        for ($i = 0; $i <= self::MAX_START_DAY_OFFSET; $i++) {
            $options[$i] = self::formatDayOffset($i);
        }

        return $options;
    }

    /**
     * Get the day offset options for the end time.
     *
     * BR-107/BR-111: 0 (same day) or 1 (day before)
     *
     * @return array<int, string>
     */
    public static function getEndDayOffsetOptions(): array
    {
        $options = [];
        for ($i = 0; $i <= self::MAX_END_DAY_OFFSET; $i++) {
            $options[$i] = self::formatDayOffset($i);
        }

        return $options;
    }

    /**
     * Check if this schedule entry has a delivery interval configured.
     *
     * F-100: Delivery/Pickup Time Interval Configuration
     */
    public function hasDeliveryInterval(): bool
    {
        return $this->delivery_enabled
            && $this->delivery_start_time !== null
            && $this->delivery_end_time !== null;
    }

    /**
     * Check if this schedule entry has a pickup interval configured.
     *
     * F-100: Delivery/Pickup Time Interval Configuration
     */
    public function hasPickupInterval(): bool
    {
        return $this->pickup_enabled
            && $this->pickup_start_time !== null
            && $this->pickup_end_time !== null;
    }

    /**
     * Get a human-readable summary of the delivery interval.
     *
     * F-100: BR-116 — delivery is always on the open day (no day offset).
     */
    public function getDeliveryIntervalSummaryAttribute(): ?string
    {
        if (! $this->hasDeliveryInterval()) {
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
     *
     * F-100: BR-116 — pickup is always on the open day (no day offset).
     */
    public function getPickupIntervalSummaryAttribute(): ?string
    {
        if (! $this->hasPickupInterval()) {
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
     * F-100: BR-117/BR-118 — delivery/pickup start must be at or after
     * order interval end. This returns the end time on the open day.
     * If order_end_day_offset > 0, the order ends before the open day,
     * so any time on the open day is valid (returns 0 = midnight).
     */
    public function getOrderEndTimeInMinutes(): ?int
    {
        if (! $this->hasOrderInterval()) {
            return null;
        }

        // If order ends before the open day (offset > 0), any time on open day is valid
        if ($this->order_end_day_offset > 0) {
            return 0;
        }

        // Order ends on the same day — parse the time
        $parts = explode(':', $this->order_end_time);

        return ((int) $parts[0] * 60) + (int) ($parts[1] ?? 0);
    }
}
