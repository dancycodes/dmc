<?php

namespace App\Services;

use App\Models\CookSchedule;
use App\Models\Tenant;

/**
 * F-098: Cook Day Schedule Creation
 * F-099: Order Time Interval Configuration
 *
 * Service layer handling all business logic for cook schedule management.
 * Encapsulates validation of per-day limits, position assignment,
 * schedule data retrieval, and order interval configuration.
 */
class CookScheduleService
{
    /**
     * Get all schedule entries for a tenant, grouped by day.
     *
     * @return array<string, array<int, CookSchedule>>
     */
    public function getSchedulesByDay(Tenant $tenant): array
    {
        $schedules = CookSchedule::query()
            ->forTenant($tenant->id)
            ->orderBy('day_of_week')
            ->orderBy('position')
            ->get();

        $grouped = [];
        foreach (CookSchedule::DAYS_OF_WEEK as $day) {
            $grouped[$day] = $schedules->where('day_of_week', $day)->values()->all();
        }

        return $grouped;
    }

    /**
     * Count entries for a specific day within a tenant.
     */
    public function countEntriesForDay(Tenant $tenant, string $day): int
    {
        return CookSchedule::query()
            ->forTenant($tenant->id)
            ->forDay($day)
            ->count();
    }

    /**
     * Check if a day has reached the maximum number of entries.
     *
     * BR-100: Maximum entries per day is configurable (default 3).
     */
    public function isDayAtLimit(Tenant $tenant, string $day): bool
    {
        return $this->countEntriesForDay($tenant, $day) >= CookSchedule::MAX_ENTRIES_PER_DAY;
    }

    /**
     * Get the next available position for a day.
     */
    public function getNextPosition(Tenant $tenant, string $day): int
    {
        $maxPosition = CookSchedule::query()
            ->forTenant($tenant->id)
            ->forDay($day)
            ->max('position');

        return ($maxPosition ?? 0) + 1;
    }

    /**
     * Create a new schedule entry.
     *
     * BR-098: Entry belongs to a single day of the week
     * BR-099: Entry has an availability flag
     * BR-100: Maximum entries per day enforced
     * BR-102: Tenant-scoped
     * BR-105: Label defaults to "Slot N" based on position
     *
     * @return array{success: bool, schedule?: CookSchedule, error?: string}
     */
    public function createScheduleEntry(
        Tenant $tenant,
        string $dayOfWeek,
        bool $isAvailable,
        ?string $label = null,
    ): array {
        // BR-100: Check per-day limit
        if ($this->isDayAtLimit($tenant, $dayOfWeek)) {
            $maxEntries = CookSchedule::MAX_ENTRIES_PER_DAY;

            return [
                'success' => false,
                'error' => __('Maximum of :max schedule entries per day has been reached.', ['max' => $maxEntries]),
            ];
        }

        $position = $this->getNextPosition($tenant, $dayOfWeek);

        // BR-105: Clean up label
        $label = ! empty(trim($label ?? '')) ? trim($label) : null;

        $schedule = CookSchedule::create([
            'tenant_id' => $tenant->id,
            'day_of_week' => $dayOfWeek,
            'is_available' => $isAvailable,
            'label' => $label,
            'position' => $position,
        ]);

        return [
            'success' => true,
            'schedule' => $schedule,
        ];
    }

    /**
     * Check if there are any schedule entries for a tenant.
     */
    public function hasAnySchedules(Tenant $tenant): bool
    {
        return CookSchedule::query()
            ->forTenant($tenant->id)
            ->exists();
    }

    /**
     * Get total schedule count for a tenant.
     */
    public function getTotalScheduleCount(Tenant $tenant): int
    {
        return CookSchedule::query()
            ->forTenant($tenant->id)
            ->count();
    }

    /**
     * Update the order time interval for a schedule entry.
     *
     * BR-106: Start = time + day offset (0-7)
     * BR-107: End = time + day offset (0-1)
     * BR-108: Start must be chronologically before end
     * BR-110: Start day offset max 7
     * BR-111: End day offset max 1
     * BR-112: Only available entries can have order intervals
     *
     * @return array{success: bool, schedule?: CookSchedule, error?: string}
     */
    public function updateOrderInterval(
        CookSchedule $schedule,
        string $orderStartTime,
        int $orderStartDayOffset,
        string $orderEndTime,
        int $orderEndDayOffset,
    ): array {
        // BR-112: Only available schedule entries can have order intervals
        if (! $schedule->is_available) {
            return [
                'success' => false,
                'error' => __('Order intervals can only be configured for available schedule entries.'),
            ];
        }

        // BR-110: Start day offset max 7
        if ($orderStartDayOffset > CookSchedule::MAX_START_DAY_OFFSET) {
            return [
                'success' => false,
                'error' => __('Start day offset cannot exceed :max days before.', ['max' => CookSchedule::MAX_START_DAY_OFFSET]),
            ];
        }

        // BR-111: End day offset max 1
        if ($orderEndDayOffset > CookSchedule::MAX_END_DAY_OFFSET) {
            return [
                'success' => false,
                'error' => __('End day offset cannot exceed :max day before.', ['max' => CookSchedule::MAX_END_DAY_OFFSET]),
            ];
        }

        // BR-108: Validate chronological order
        if (! $this->isIntervalChronologicallyValid(
            $orderStartTime,
            $orderStartDayOffset,
            $orderEndTime,
            $orderEndDayOffset,
        )) {
            return [
                'success' => false,
                'error' => __('The order interval end must be after the start. Please adjust the times or day offsets.'),
            ];
        }

        $schedule->update([
            'order_start_time' => $orderStartTime,
            'order_start_day_offset' => $orderStartDayOffset,
            'order_end_time' => $orderEndTime,
            'order_end_day_offset' => $orderEndDayOffset,
        ]);

        return [
            'success' => true,
            'schedule' => $schedule->fresh(),
        ];
    }

    /**
     * Remove the order interval from a schedule entry.
     *
     * @return array{success: bool, schedule: CookSchedule}
     */
    public function removeOrderInterval(CookSchedule $schedule): array
    {
        $schedule->update([
            'order_start_time' => null,
            'order_start_day_offset' => 0,
            'order_end_time' => null,
            'order_end_day_offset' => 0,
        ]);

        return [
            'success' => true,
            'schedule' => $schedule->fresh(),
        ];
    }

    /**
     * Check if the interval is chronologically valid.
     *
     * BR-108: Start datetime must be chronologically before end datetime.
     * We resolve both to "minutes relative to open day 00:00":
     * - Same day 08:00 = +480
     * - Day before 18:00 = -1440 + 1080 = -360
     * - 2 days before 12:00 = -2880 + 720 = -2160
     *
     * Start must have a LOWER value (earlier in time) than end.
     */
    public function isIntervalChronologicallyValid(
        string $startTime,
        int $startDayOffset,
        string $endTime,
        int $endDayOffset,
    ): bool {
        $startAbsolute = $this->resolveToAbsoluteMinutes($startTime, $startDayOffset);
        $endAbsolute = $this->resolveToAbsoluteMinutes($endTime, $endDayOffset);

        // Start must be strictly before end
        return $startAbsolute < $endAbsolute;
    }

    /**
     * Resolve a time + day offset to absolute minutes relative to open day 00:00.
     *
     * Examples:
     * - Same day 08:00, offset 0 = 480
     * - Day before 18:00, offset 1 = -1440 + 1080 = -360
     * - 2 days before 12:00, offset 2 = -2880 + 720 = -2160
     *
     * Lower value = earlier in time.
     */
    private function resolveToAbsoluteMinutes(string $time, int $dayOffset): int
    {
        $parts = explode(':', $time);
        $hours = (int) $parts[0];
        $minutes = (int) ($parts[1] ?? 0);
        $minutesIntoDay = ($hours * 60) + $minutes;

        return $minutesIntoDay - ($dayOffset * 1440);
    }

    /**
     * Get summary data for the schedule page.
     *
     * @return array{total: int, available: int, unavailable: int, days_covered: int}
     */
    public function getScheduleSummary(Tenant $tenant): array
    {
        $schedules = CookSchedule::query()
            ->forTenant($tenant->id)
            ->get();

        return [
            'total' => $schedules->count(),
            'available' => $schedules->where('is_available', true)->count(),
            'unavailable' => $schedules->where('is_available', false)->count(),
            'days_covered' => $schedules->pluck('day_of_week')->unique()->count(),
        ];
    }
}
