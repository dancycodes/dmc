<?php

namespace App\Services;

use App\Models\CookSchedule;
use App\Models\Tenant;

/**
 * F-098: Cook Day Schedule Creation
 * F-099: Order Time Interval Configuration
 * F-100: Delivery/Pickup Time Interval Configuration
 * F-107: Schedule Validation Rules (overlap checks, comprehensive validation)
 *
 * Service layer handling all business logic for cook schedule management.
 * Encapsulates validation of per-day limits, position assignment,
 * schedule data retrieval, order interval configuration, and
 * delivery/pickup interval configuration.
 */
class CookScheduleService
{
    public function __construct(
        private ScheduleValidationService $validationService,
    ) {}

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

        // F-107: Comprehensive validation via ScheduleValidationService
        // BR-173, BR-179, BR-180, BR-181
        $validation = $this->validationService->validateOrderIntervalUpdate(
            $schedule,
            $orderStartTime,
            $orderStartDayOffset,
            $orderEndTime,
            $orderEndDayOffset,
        );

        if (! $validation['valid']) {
            $firstError = array_values($validation['errors'])[0];

            return [
                'success' => false,
                'error' => $firstError,
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
     * Update the delivery/pickup time intervals for a schedule entry.
     *
     * F-100: Delivery/Pickup Time Interval Configuration
     *
     * BR-116: Both intervals must be on the open day (day offset 0)
     * BR-117: Delivery start >= order interval end time
     * BR-118: Pickup start >= order interval end time
     * BR-119: Delivery end > delivery start
     * BR-120: Pickup end > pickup start
     * BR-121: At least one of delivery or pickup must be enabled (if entry is available)
     * BR-124: Order interval must be configured before delivery/pickup
     * BR-126: Changes logged via Spatie Activitylog
     *
     * @return array{success: bool, schedule?: CookSchedule, error?: string, field?: string}
     */
    public function updateDeliveryPickupInterval(
        CookSchedule $schedule,
        bool $deliveryEnabled,
        ?string $deliveryStartTime,
        ?string $deliveryEndTime,
        bool $pickupEnabled,
        ?string $pickupStartTime,
        ?string $pickupEndTime,
    ): array {
        // BR-112: Only available schedule entries can have intervals
        if (! $schedule->is_available) {
            return [
                'success' => false,
                'error' => __('Delivery/pickup intervals can only be configured for available schedule entries.'),
                'field' => 'delivery_enabled',
            ];
        }

        // BR-124: Order interval must be configured first
        if (! $schedule->hasOrderInterval()) {
            return [
                'success' => false,
                'error' => __('The order interval must be configured before setting delivery/pickup intervals.'),
                'field' => 'delivery_enabled',
            ];
        }

        // BR-121: At least one must be enabled
        if (! $deliveryEnabled && ! $pickupEnabled) {
            return [
                'success' => false,
                'error' => __('At least one of delivery or pickup must be enabled.'),
                'field' => 'delivery_enabled',
            ];
        }

        $orderEndMinutes = $schedule->getOrderEndTimeInMinutes();

        // Validate delivery interval if enabled
        if ($deliveryEnabled) {
            $deliveryValidation = $this->validateTimeWindow(
                'delivery',
                $deliveryStartTime,
                $deliveryEndTime,
                $orderEndMinutes,
            );

            if ($deliveryValidation !== null) {
                return $deliveryValidation;
            }
        }

        // Validate pickup interval if enabled
        if ($pickupEnabled) {
            $pickupValidation = $this->validateTimeWindow(
                'pickup',
                $pickupStartTime,
                $pickupEndTime,
                $orderEndMinutes,
            );

            if ($pickupValidation !== null) {
                return $pickupValidation;
            }
        }

        // F-107 BR-179: Check for overlapping delivery/pickup windows
        $overlapCheck = $this->validationService->checkForOverlaps(
            $schedule->tenant_id,
            $schedule->day_of_week,
            null,
            null,
            null,
            null,
            $deliveryEnabled ? $deliveryStartTime : null,
            $deliveryEnabled ? $deliveryEndTime : null,
            $pickupEnabled ? $pickupStartTime : null,
            $pickupEnabled ? $pickupEndTime : null,
            $schedule->id,
        );

        if ($overlapCheck['overlapping']) {
            $field = $overlapCheck['type'] === 'delivery' ? 'delivery_start_time' : 'pickup_start_time';

            return [
                'success' => false,
                'error' => $overlapCheck['message'],
                'field' => $field,
            ];
        }

        $schedule->update([
            'delivery_enabled' => $deliveryEnabled,
            'delivery_start_time' => $deliveryEnabled ? $deliveryStartTime : null,
            'delivery_end_time' => $deliveryEnabled ? $deliveryEndTime : null,
            'pickup_enabled' => $pickupEnabled,
            'pickup_start_time' => $pickupEnabled ? $pickupStartTime : null,
            'pickup_end_time' => $pickupEnabled ? $pickupEndTime : null,
        ]);

        return [
            'success' => true,
            'schedule' => $schedule->fresh(),
        ];
    }

    /**
     * Validate a delivery or pickup time window.
     *
     * @return array{success: bool, error: string, field: string}|null Null if valid
     */
    private function validateTimeWindow(
        string $type,
        ?string $startTime,
        ?string $endTime,
        ?int $orderEndMinutes,
    ): ?array {
        $typeLabel = $type === 'delivery' ? __('Delivery') : __('Pickup');

        // Times required when enabled
        if (empty($startTime) || empty($endTime)) {
            return [
                'success' => false,
                'error' => __(':type start and end times are required when enabled.', ['type' => $typeLabel]),
                'field' => $type.'_start_time',
            ];
        }

        $startMinutes = $this->timeToMinutes($startTime);
        $endMinutes = $this->timeToMinutes($endTime);

        // BR-119/BR-120: End must be after start
        if ($endMinutes <= $startMinutes) {
            return [
                'success' => false,
                'error' => __(':type end time must be after the start time.', ['type' => $typeLabel]),
                'field' => $type.'_end_time',
            ];
        }

        // BR-117/BR-118: Start must be at or after order interval end time
        if ($orderEndMinutes !== null && $startMinutes < $orderEndMinutes) {
            $orderEndFormatted = date('g:i A', mktime((int) ($orderEndMinutes / 60), $orderEndMinutes % 60));

            return [
                'success' => false,
                'error' => __(':type start time must be at or after the order interval end time (:time).', [
                    'type' => $typeLabel,
                    'time' => $orderEndFormatted,
                ]),
                'field' => $type.'_start_time',
            ];
        }

        return null;
    }

    /**
     * Convert HH:MM time string to minutes from midnight.
     */
    private function timeToMinutes(string $time): int
    {
        $parts = explode(':', $time);

        return ((int) $parts[0] * 60) + (int) ($parts[1] ?? 0);
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
