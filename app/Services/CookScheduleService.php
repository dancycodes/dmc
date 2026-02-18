<?php

namespace App\Services;

use App\Models\CookSchedule;
use App\Models\Tenant;

/**
 * F-098: Cook Day Schedule Creation
 *
 * Service layer handling all business logic for cook schedule management.
 * Encapsulates validation of per-day limits, position assignment, and
 * schedule data retrieval.
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
