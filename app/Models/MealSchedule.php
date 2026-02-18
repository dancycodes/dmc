<?php

namespace App\Models;

use App\Traits\LogsActivityTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * F-106: Meal Schedule Override
 *
 * Represents a meal-specific schedule entry that overrides the cook's
 * default tenant-level schedule for a particular meal. Uses the same
 * structure as CookSchedule (F-098/F-099/F-100) with the addition
 * of a meal_id foreign key.
 *
 * When a meal has MealSchedule entries, it operates on its own schedule
 * independently from the cook's default schedule.
 */
class MealSchedule extends Model
{
    /** @use HasFactory<\Database\Factories\MealScheduleFactory> */
    use HasFactory, LogsActivityTrait;

    /**
     * The table associated with the model.
     */
    protected $table = 'meal_schedules';

    /**
     * BR-166: Same max entries per day as cook schedule.
     */
    public const MAX_ENTRIES_PER_DAY = 3;

    /**
     * BR-166: Same max start day offset as cook schedule.
     */
    public const MAX_START_DAY_OFFSET = 7;

    /**
     * BR-166: Same max end day offset as cook schedule.
     */
    public const MAX_END_DAY_OFFSET = 1;

    /**
     * Valid days of the week (reuses CookSchedule constants).
     *
     * @var list<string>
     */
    public const DAYS_OF_WEEK = CookSchedule::DAYS_OF_WEEK;

    /**
     * Day labels for display (reuses CookSchedule constants).
     *
     * @var array<string, string>
     */
    public const DAY_LABELS = CookSchedule::DAY_LABELS;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'meal_id',
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
     * Get the meal this schedule entry belongs to.
     */
    public function meal(): BelongsTo
    {
        return $this->belongsTo(Meal::class);
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
     * Defaults to "Slot N" based on position when label is empty.
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
     * Scope to filter by meal.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeForMeal($query, int $mealId)
    {
        return $query->where('meal_id', $mealId);
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
     * Check if this schedule entry has a delivery interval configured.
     */
    public function hasDeliveryInterval(): bool
    {
        return $this->delivery_enabled
            && $this->delivery_start_time !== null
            && $this->delivery_end_time !== null;
    }

    /**
     * Check if this schedule entry has a pickup interval configured.
     */
    public function hasPickupInterval(): bool
    {
        return $this->pickup_enabled
            && $this->pickup_start_time !== null
            && $this->pickup_end_time !== null;
    }

    /**
     * Get the order interval end time in minutes from midnight.
     */
    public function getOrderEndTimeInMinutes(): ?int
    {
        if (! $this->hasOrderInterval()) {
            return null;
        }

        if ($this->order_end_day_offset > 0) {
            return 0;
        }

        $parts = explode(':', $this->order_end_time);

        return ((int) $parts[0] * 60) + (int) ($parts[1] ?? 0);
    }

    /**
     * Get a human-readable summary of the order interval.
     */
    public function getOrderIntervalSummaryAttribute(): ?string
    {
        if (! $this->hasOrderInterval()) {
            return null;
        }

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
     * Format a time value for display (12-hour format with AM/PM).
     */
    private function formatTime(string $time): string
    {
        return date('g:i A', strtotime($time));
    }

    /**
     * Get the day offset options for the start time.
     *
     * @return array<int, string>
     */
    public static function getStartDayOffsetOptions(): array
    {
        return CookSchedule::getStartDayOffsetOptions();
    }

    /**
     * Get the day offset options for the end time.
     *
     * @return array<int, string>
     */
    public static function getEndDayOffsetOptions(): array
    {
        return CookSchedule::getEndDayOffsetOptions();
    }
}
