<?php

namespace App\Services;

use App\Models\CookSchedule;

/**
 * F-107: Schedule Validation Rules
 *
 * Centralized validation service for all schedule-related operations.
 * Enforces comprehensive rules for time formats, interval ordering,
 * overlapping entries, day-before limits, and consistency between
 * order, delivery, and pickup intervals.
 *
 * BR-172 through BR-186: All schedule validation business rules.
 *
 * Applies equally to:
 * - CookSchedule (F-098, F-099, F-100)
 * - ScheduleTemplate (F-101, F-103)
 * - MealSchedule (F-106 — forward-compatible)
 */
class ScheduleValidationService
{
    /**
     * Validate that a time string is in valid 24-hour format (HH:MM).
     *
     * BR-172: Time format must be 24-hour (HH:MM), values from 00:00 to 23:59
     */
    public function isValidTimeFormat(string $time): bool
    {
        if (! preg_match('/^\d{2}:\d{2}$/', $time)) {
            return false;
        }

        $parts = explode(':', $time);
        $hours = (int) $parts[0];
        $minutes = (int) $parts[1];

        return $hours >= 0 && $hours <= 23 && $minutes >= 0 && $minutes <= 59;
    }

    /**
     * Validate that the order interval is chronologically valid.
     *
     * BR-173: Order interval start must be chronologically before end
     *         (accounting for day offsets).
     */
    public function isOrderIntervalValid(
        string $startTime,
        int $startDayOffset,
        string $endTime,
        int $endDayOffset,
    ): bool {
        $startAbsolute = $this->resolveToAbsoluteMinutes($startTime, $startDayOffset);
        $endAbsolute = $this->resolveToAbsoluteMinutes($endTime, $endDayOffset);

        return $startAbsolute < $endAbsolute;
    }

    /**
     * Validate that the order interval end is before or equal to delivery start.
     *
     * BR-174: Order interval end must be chronologically before or equal to
     *         delivery start time.
     *
     * @return array{valid: bool, message?: string}
     */
    public function validateOrderEndBeforeDeliveryStart(
        string $orderEndTime,
        int $orderEndDayOffset,
        string $deliveryStartTime,
    ): array {
        $orderEndMinutes = $this->getOrderEndMinutesOnOpenDay($orderEndTime, $orderEndDayOffset);
        $deliveryStartMinutes = $this->timeToMinutes($deliveryStartTime);

        if ($deliveryStartMinutes < $orderEndMinutes) {
            $orderEndFormatted = $this->formatTimeForDisplay($orderEndTime);

            return [
                'valid' => false,
                'message' => __('Delivery must start at or after the order window closes (:time).', [
                    'time' => $orderEndFormatted,
                ]),
            ];
        }

        return ['valid' => true];
    }

    /**
     * Validate that the order interval end is before or equal to pickup start.
     *
     * BR-175: Order interval end must be chronologically before or equal to
     *         pickup start time.
     *
     * @return array{valid: bool, message?: string}
     */
    public function validateOrderEndBeforePickupStart(
        string $orderEndTime,
        int $orderEndDayOffset,
        string $pickupStartTime,
    ): array {
        $orderEndMinutes = $this->getOrderEndMinutesOnOpenDay($orderEndTime, $orderEndDayOffset);
        $pickupStartMinutes = $this->timeToMinutes($pickupStartTime);

        if ($pickupStartMinutes < $orderEndMinutes) {
            $orderEndFormatted = $this->formatTimeForDisplay($orderEndTime);

            return [
                'valid' => false,
                'message' => __('Pickup must start at or after the order window closes (:time).', [
                    'time' => $orderEndFormatted,
                ]),
            ];
        }

        return ['valid' => true];
    }

    /**
     * Validate that delivery start is before delivery end.
     *
     * BR-176: Delivery start must be before delivery end (both on the open day).
     */
    public function isDeliveryIntervalValid(string $startTime, string $endTime): bool
    {
        return $this->timeToMinutes($endTime) > $this->timeToMinutes($startTime);
    }

    /**
     * Validate that pickup start is before pickup end.
     *
     * BR-177: Pickup start must be before pickup end (both on the open day).
     */
    public function isPickupIntervalValid(string $startTime, string $endTime): bool
    {
        return $this->timeToMinutes($endTime) > $this->timeToMinutes($startTime);
    }

    /**
     * Check for overlapping schedule entries on the same day.
     *
     * BR-179: No overlapping schedule entries for the same day — delivery windows
     *         must not overlap, pickup windows must not overlap, and order windows
     *         must not overlap across entries for the same day.
     *
     * Checks all three window types (order, delivery, pickup) for overlaps with
     * existing entries on the same day for the same tenant.
     *
     * @return array{overlapping: bool, type?: string, conflicting_entry?: CookSchedule, message?: string}
     */
    public function checkForOverlaps(
        int $tenantId,
        string $dayOfWeek,
        ?string $orderStartTime,
        ?int $orderStartDayOffset,
        ?string $orderEndTime,
        ?int $orderEndDayOffset,
        ?string $deliveryStartTime,
        ?string $deliveryEndTime,
        ?string $pickupStartTime,
        ?string $pickupEndTime,
        ?int $excludeEntryId = null,
    ): array {
        $existingEntries = CookSchedule::query()
            ->forTenant($tenantId)
            ->forDay($dayOfWeek)
            ->when($excludeEntryId, fn ($q) => $q->where('id', '!=', $excludeEntryId))
            ->get();

        foreach ($existingEntries as $entry) {
            // Check order window overlap
            if ($orderStartTime !== null && $orderEndTime !== null && $entry->hasOrderInterval()) {
                $newOrderStart = $this->resolveToAbsoluteMinutes($orderStartTime, $orderStartDayOffset ?? 0);
                $newOrderEnd = $this->resolveToAbsoluteMinutes($orderEndTime, $orderEndDayOffset ?? 0);
                $existingOrderStart = $this->resolveToAbsoluteMinutes($entry->order_start_time, $entry->order_start_day_offset);
                $existingOrderEnd = $this->resolveToAbsoluteMinutes($entry->order_end_time, $entry->order_end_day_offset);

                if ($this->windowsOverlap($newOrderStart, $newOrderEnd, $existingOrderStart, $existingOrderEnd)) {
                    return [
                        'overlapping' => true,
                        'type' => 'order',
                        'conflicting_entry' => $entry,
                        'message' => __('Order window overlaps with an existing :day schedule (:label).', [
                            'day' => __(CookSchedule::DAY_LABELS[$dayOfWeek] ?? $dayOfWeek),
                            'label' => $entry->display_label,
                        ]),
                    ];
                }
            }

            // Check delivery window overlap
            if ($deliveryStartTime !== null && $deliveryEndTime !== null && $entry->hasDeliveryInterval()) {
                $newDeliveryStart = $this->timeToMinutes($deliveryStartTime);
                $newDeliveryEnd = $this->timeToMinutes($deliveryEndTime);
                $existingDeliveryStart = $this->timeToMinutes($entry->delivery_start_time);
                $existingDeliveryEnd = $this->timeToMinutes($entry->delivery_end_time);

                if ($this->windowsOverlap($newDeliveryStart, $newDeliveryEnd, $existingDeliveryStart, $existingDeliveryEnd)) {
                    $existingStartFormatted = $this->formatTimeForDisplay($entry->delivery_start_time);
                    $existingEndFormatted = $this->formatTimeForDisplay($entry->delivery_end_time);

                    return [
                        'overlapping' => true,
                        'type' => 'delivery',
                        'conflicting_entry' => $entry,
                        'message' => __('Delivery window overlaps with an existing :day schedule (:startTime - :endTime).', [
                            'day' => __(CookSchedule::DAY_LABELS[$dayOfWeek] ?? $dayOfWeek),
                            'startTime' => $existingStartFormatted,
                            'endTime' => $existingEndFormatted,
                        ]),
                    ];
                }
            }

            // Check pickup window overlap
            if ($pickupStartTime !== null && $pickupEndTime !== null && $entry->hasPickupInterval()) {
                $newPickupStart = $this->timeToMinutes($pickupStartTime);
                $newPickupEnd = $this->timeToMinutes($pickupEndTime);
                $existingPickupStart = $this->timeToMinutes($entry->pickup_start_time);
                $existingPickupEnd = $this->timeToMinutes($entry->pickup_end_time);

                if ($this->windowsOverlap($newPickupStart, $newPickupEnd, $existingPickupStart, $existingPickupEnd)) {
                    $existingStartFormatted = $this->formatTimeForDisplay($entry->pickup_start_time);
                    $existingEndFormatted = $this->formatTimeForDisplay($entry->pickup_end_time);

                    return [
                        'overlapping' => true,
                        'type' => 'pickup',
                        'conflicting_entry' => $entry,
                        'message' => __('Pickup window overlaps with an existing :day schedule (:startTime - :endTime).', [
                            'day' => __(CookSchedule::DAY_LABELS[$dayOfWeek] ?? $dayOfWeek),
                            'startTime' => $existingStartFormatted,
                            'endTime' => $existingEndFormatted,
                        ]),
                    ];
                }
            }
        }

        return ['overlapping' => false];
    }

    /**
     * Validate that the order start day offset does not exceed the maximum.
     *
     * BR-180: Order start day offset cannot exceed 7.
     */
    public function isStartDayOffsetValid(int $offset): bool
    {
        return $offset >= 0 && $offset <= CookSchedule::MAX_START_DAY_OFFSET;
    }

    /**
     * Validate that the order end day offset is valid.
     *
     * BR-181: Order end day offset can be 0 (same day) or 1 (day before) only.
     */
    public function isEndDayOffsetValid(int $offset): bool
    {
        return $offset >= 0 && $offset <= CookSchedule::MAX_END_DAY_OFFSET;
    }

    /**
     * Check if a cook has any available schedule entries.
     *
     * BR-182: At least one schedule entry with is_available = true is required
     *         if the cook wants to accept orders.
     */
    public function hasAvailableSchedules(int $tenantId): bool
    {
        return CookSchedule::query()
            ->forTenant($tenantId)
            ->available()
            ->exists();
    }

    /**
     * Validate that at least one of delivery or pickup is enabled.
     *
     * BR-183: At least one of delivery or pickup must be enabled per
     *         available schedule entry.
     */
    public function hasDeliveryOrPickup(bool $deliveryEnabled, bool $pickupEnabled): bool
    {
        return $deliveryEnabled || $pickupEnabled;
    }

    /**
     * Run comprehensive validation for an order interval update.
     *
     * BR-173, BR-174, BR-175, BR-179, BR-180, BR-181
     *
     * @return array{valid: bool, errors: array<string, string>}
     */
    public function validateOrderIntervalUpdate(
        CookSchedule $schedule,
        string $orderStartTime,
        int $orderStartDayOffset,
        string $orderEndTime,
        int $orderEndDayOffset,
    ): array {
        $errors = [];

        // BR-180: Start day offset max 7
        if (! $this->isStartDayOffsetValid($orderStartDayOffset)) {
            $errors['order_start_day_offset'] = __('Order window cannot start more than :max days before the open day.', [
                'max' => CookSchedule::MAX_START_DAY_OFFSET,
            ]);
        }

        // BR-181: End day offset max 1
        if (! $this->isEndDayOffsetValid($orderEndDayOffset)) {
            $errors['order_end_day_offset'] = __('Order end day offset cannot exceed :max.', [
                'max' => CookSchedule::MAX_END_DAY_OFFSET,
            ]);
        }

        // BR-173: Chronological order
        if (empty($errors) && ! $this->isOrderIntervalValid($orderStartTime, $orderStartDayOffset, $orderEndTime, $orderEndDayOffset)) {
            $errors['order_start_time'] = __('The order interval end must be after the start. Please adjust the times or day offsets.');
        }

        // BR-179: Check for overlapping order windows with other entries
        if (empty($errors)) {
            $overlapCheck = $this->checkForOverlaps(
                $schedule->tenant_id,
                $schedule->day_of_week,
                $orderStartTime,
                $orderStartDayOffset,
                $orderEndTime,
                $orderEndDayOffset,
                null,
                null,
                null,
                null,
                $schedule->id,
            );

            if ($overlapCheck['overlapping']) {
                $errors['order_start_time'] = $overlapCheck['message'];
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * Run comprehensive validation for delivery/pickup interval update.
     *
     * BR-174, BR-175, BR-176, BR-177, BR-178, BR-179, BR-183
     *
     * @return array{valid: bool, errors: array<string, string>}
     */
    public function validateDeliveryPickupUpdate(
        CookSchedule $schedule,
        bool $deliveryEnabled,
        ?string $deliveryStartTime,
        ?string $deliveryEndTime,
        bool $pickupEnabled,
        ?string $pickupStartTime,
        ?string $pickupEndTime,
    ): array {
        $errors = [];

        // BR-183: At least one must be enabled
        if (! $this->hasDeliveryOrPickup($deliveryEnabled, $pickupEnabled)) {
            $errors['delivery_enabled'] = __('At least one of delivery or pickup must be enabled.');
        }

        // Validate delivery interval
        if ($deliveryEnabled && $deliveryStartTime && $deliveryEndTime) {
            // BR-176: Delivery start before end
            if (! $this->isDeliveryIntervalValid($deliveryStartTime, $deliveryEndTime)) {
                $errors['delivery_end_time'] = __('Delivery end time must be after the start time.');
            }

            // BR-174: Order end before delivery start
            if ($schedule->hasOrderInterval()) {
                $orderEndCheck = $this->validateOrderEndBeforeDeliveryStart(
                    $schedule->order_end_time,
                    $schedule->order_end_day_offset,
                    $deliveryStartTime,
                );
                if (! $orderEndCheck['valid']) {
                    $errors['delivery_start_time'] = $orderEndCheck['message'];
                }
            }
        }

        // Validate pickup interval
        if ($pickupEnabled && $pickupStartTime && $pickupEndTime) {
            // BR-177: Pickup start before end
            if (! $this->isPickupIntervalValid($pickupStartTime, $pickupEndTime)) {
                $errors['pickup_end_time'] = __('Pickup end time must be after the start time.');
            }

            // BR-175: Order end before pickup start
            if ($schedule->hasOrderInterval()) {
                $orderEndCheck = $this->validateOrderEndBeforePickupStart(
                    $schedule->order_end_time,
                    $schedule->order_end_day_offset,
                    $pickupStartTime,
                );
                if (! $orderEndCheck['valid']) {
                    $errors['pickup_start_time'] = $orderEndCheck['message'];
                }
            }
        }

        // BR-179: Check for overlapping delivery/pickup windows
        if (empty($errors)) {
            $overlapCheck = $this->checkForOverlaps(
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
                $errors[$field] = $overlapCheck['message'];
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * Check if two time windows overlap.
     *
     * Two windows [A_start, A_end) and [B_start, B_end) overlap when
     * A_start < B_end AND B_start < A_end.
     *
     * Adjacent windows (one ends exactly when another starts) are NOT
     * considered overlapping — this is intentional per the spec edge case.
     */
    public function windowsOverlap(int $startA, int $endA, int $startB, int $endB): bool
    {
        return $startA < $endB && $startB < $endA;
    }

    /**
     * Resolve a time + day offset to absolute minutes relative to open day 00:00.
     *
     * Same day 08:00, offset 0 = 480
     * Day before 18:00, offset 1 = -1440 + 1080 = -360
     * 2 days before 12:00, offset 2 = -2880 + 720 = -2160
     *
     * Lower value = earlier in time.
     */
    public function resolveToAbsoluteMinutes(string $time, int $dayOffset): int
    {
        $minutesIntoDay = $this->timeToMinutes($time);

        return $minutesIntoDay - ($dayOffset * 1440);
    }

    /**
     * Convert HH:MM time string to minutes from midnight.
     */
    public function timeToMinutes(string $time): int
    {
        $parts = explode(':', $time);

        return ((int) $parts[0] * 60) + (int) ($parts[1] ?? 0);
    }

    /**
     * Get order end time in minutes on the open day.
     *
     * If order ends before the open day (offset > 0), returns 0 (midnight),
     * meaning any time on the open day is valid.
     */
    public function getOrderEndMinutesOnOpenDay(string $orderEndTime, int $orderEndDayOffset): int
    {
        if ($orderEndDayOffset > 0) {
            return 0;
        }

        return $this->timeToMinutes($orderEndTime);
    }

    /**
     * Format a time string for user-friendly display (12-hour with AM/PM).
     */
    public function formatTimeForDisplay(string $time): string
    {
        return date('g:i A', strtotime($time));
    }
}
