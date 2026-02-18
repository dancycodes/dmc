<?php

namespace App\Services;

use App\Models\CookSchedule;
use App\Models\Meal;
use App\Models\MealSchedule;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;

/**
 * F-106: Meal Schedule Override
 *
 * Service layer for meal-specific schedule override management.
 * Handles enabling/disabling custom schedules, CRUD operations for
 * schedule entries, and reverting to cook's default schedule.
 *
 * BR-162: By default, meals inherit the cook's tenant-level schedule
 * BR-163: A meal can optionally have its own custom schedule
 * BR-164: Toggle is binary — cook's schedule or custom schedule
 * BR-166: Same validation rules as cook schedule (F-098/F-099/F-100)
 * BR-167: Reverting deletes all meal-specific schedule entries
 * BR-169: Schedule entries are tenant-scoped and meal-scoped
 */
class MealScheduleService
{
    public function __construct(
        private ScheduleValidationService $validationService,
    ) {}

    /**
     * Check if a meal has a custom schedule (has any MealSchedule entries).
     *
     * BR-162/BR-163: Presence of entries determines custom schedule mode.
     */
    public function hasCustomSchedule(Meal $meal): bool
    {
        return MealSchedule::query()
            ->forMeal($meal->id)
            ->exists();
    }

    /**
     * Get all schedule entries for a meal, grouped by day.
     *
     * @return array<string, array<int, MealSchedule>>
     */
    public function getSchedulesByDay(Meal $meal): array
    {
        $schedules = MealSchedule::query()
            ->forMeal($meal->id)
            ->orderBy('day_of_week')
            ->orderBy('position')
            ->get();

        $grouped = [];
        foreach (MealSchedule::DAYS_OF_WEEK as $day) {
            $grouped[$day] = $schedules->where('day_of_week', $day)->values()->all();
        }

        return $grouped;
    }

    /**
     * Get cook's default schedule for display when meal uses cook's schedule.
     *
     * @return array<string, array<int, CookSchedule>>
     */
    public function getCookSchedulesByDay(Tenant $tenant): array
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
     * Count entries for a specific day within a meal.
     */
    public function countEntriesForDay(Meal $meal, string $day): int
    {
        return MealSchedule::query()
            ->forMeal($meal->id)
            ->forDay($day)
            ->count();
    }

    /**
     * Check if a day has reached the maximum number of entries.
     */
    public function isDayAtLimit(Meal $meal, string $day): bool
    {
        return $this->countEntriesForDay($meal, $day) >= MealSchedule::MAX_ENTRIES_PER_DAY;
    }

    /**
     * Get the next available position for a day.
     */
    public function getNextPosition(Meal $meal, string $day): int
    {
        $maxPosition = MealSchedule::query()
            ->forMeal($meal->id)
            ->forDay($day)
            ->max('position');

        return ($maxPosition ?? 0) + 1;
    }

    /**
     * Create a new meal schedule entry.
     *
     * BR-166: Same rules as cook schedule (F-098)
     *
     * @return array{success: bool, schedule?: MealSchedule, error?: string}
     */
    public function createScheduleEntry(
        Tenant $tenant,
        Meal $meal,
        string $dayOfWeek,
        bool $isAvailable,
        ?string $label = null,
    ): array {
        if ($this->isDayAtLimit($meal, $dayOfWeek)) {
            $maxEntries = MealSchedule::MAX_ENTRIES_PER_DAY;

            return [
                'success' => false,
                'error' => __('Maximum of :max schedule entries per day has been reached.', ['max' => $maxEntries]),
            ];
        }

        $position = $this->getNextPosition($meal, $dayOfWeek);
        $label = ! empty(trim($label ?? '')) ? trim($label) : null;

        $schedule = MealSchedule::create([
            'tenant_id' => $tenant->id,
            'meal_id' => $meal->id,
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
     * Update the order time interval for a meal schedule entry.
     *
     * BR-166: Same validation as cook schedule (F-099)
     *
     * @return array{success: bool, schedule?: MealSchedule, error?: string}
     */
    public function updateOrderInterval(
        MealSchedule $schedule,
        string $orderStartTime,
        int $orderStartDayOffset,
        string $orderEndTime,
        int $orderEndDayOffset,
    ): array {
        if (! $schedule->is_available) {
            return [
                'success' => false,
                'error' => __('Order intervals can only be configured for available schedule entries.'),
            ];
        }

        // Validate chronological order
        if (! $this->validationService->isOrderIntervalValid(
            $orderStartTime, $orderStartDayOffset, $orderEndTime, $orderEndDayOffset,
        )) {
            return [
                'success' => false,
                'error' => __('The order interval end must be after the start. Please adjust the times or day offsets.'),
            ];
        }

        // Validate day offset ranges
        if (! $this->validationService->isStartDayOffsetValid($orderStartDayOffset)) {
            return [
                'success' => false,
                'error' => __('Order window cannot start more than :max days before the open day.', [
                    'max' => MealSchedule::MAX_START_DAY_OFFSET,
                ]),
            ];
        }

        if (! $this->validationService->isEndDayOffsetValid($orderEndDayOffset)) {
            return [
                'success' => false,
                'error' => __('Order end day offset cannot exceed :max.', [
                    'max' => MealSchedule::MAX_END_DAY_OFFSET,
                ]),
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
     * Update the delivery/pickup time intervals for a meal schedule entry.
     *
     * BR-166: Same validation as cook schedule (F-100)
     *
     * @return array{success: bool, schedule?: MealSchedule, error?: string, field?: string}
     */
    public function updateDeliveryPickupInterval(
        MealSchedule $schedule,
        bool $deliveryEnabled,
        ?string $deliveryStartTime,
        ?string $deliveryEndTime,
        bool $pickupEnabled,
        ?string $pickupStartTime,
        ?string $pickupEndTime,
    ): array {
        if (! $schedule->is_available) {
            return [
                'success' => false,
                'error' => __('Delivery/pickup intervals can only be configured for available schedule entries.'),
                'field' => 'delivery_enabled',
            ];
        }

        if (! $schedule->hasOrderInterval()) {
            return [
                'success' => false,
                'error' => __('The order interval must be configured before setting delivery/pickup intervals.'),
                'field' => 'delivery_enabled',
            ];
        }

        if (! $deliveryEnabled && ! $pickupEnabled) {
            return [
                'success' => false,
                'error' => __('At least one of delivery or pickup must be enabled.'),
                'field' => 'delivery_enabled',
            ];
        }

        $orderEndMinutes = $schedule->getOrderEndTimeInMinutes();

        // Validate delivery interval
        if ($deliveryEnabled) {
            $validation = $this->validateTimeWindow('delivery', $deliveryStartTime, $deliveryEndTime, $orderEndMinutes);
            if ($validation !== null) {
                return $validation;
            }
        }

        // Validate pickup interval
        if ($pickupEnabled) {
            $validation = $this->validateTimeWindow('pickup', $pickupStartTime, $pickupEndTime, $orderEndMinutes);
            if ($validation !== null) {
                return $validation;
            }
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
     * Revert a meal to the cook's default schedule.
     *
     * BR-167: Deletes all meal-specific schedule entries.
     *
     * @return array{success: bool, deleted_count: int}
     */
    public function revertToDefaultSchedule(Meal $meal): array
    {
        $deletedCount = DB::transaction(function () use ($meal) {
            return MealSchedule::query()
                ->forMeal($meal->id)
                ->delete();
        });

        return [
            'success' => true,
            'deleted_count' => $deletedCount,
        ];
    }

    /**
     * Get summary data for a meal's custom schedule.
     *
     * @return array{total: int, available: int, unavailable: int, days_covered: int}
     */
    public function getScheduleSummary(Meal $meal): array
    {
        $schedules = MealSchedule::query()
            ->forMeal($meal->id)
            ->get();

        return [
            'total' => $schedules->count(),
            'available' => $schedules->where('is_available', true)->count(),
            'unavailable' => $schedules->where('is_available', false)->count(),
            'days_covered' => $schedules->pluck('day_of_week')->unique()->count(),
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

        if (empty($startTime) || empty($endTime)) {
            return [
                'success' => false,
                'error' => __(':type start and end times are required when enabled.', ['type' => $typeLabel]),
                'field' => $type.'_start_time',
            ];
        }

        $startMinutes = $this->timeToMinutes($startTime);
        $endMinutes = $this->timeToMinutes($endTime);

        if ($endMinutes <= $startMinutes) {
            return [
                'success' => false,
                'error' => __(':type end time must be after the start time.', ['type' => $typeLabel]),
                'field' => $type.'_end_time',
            ];
        }

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
}
