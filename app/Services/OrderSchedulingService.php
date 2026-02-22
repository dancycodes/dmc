<?php

namespace App\Services;

use App\Models\CookSchedule;
use App\Models\Meal;
use App\Models\MealSchedule;
use Carbon\Carbon;

/**
 * F-148: Order Scheduling for Future Date
 *
 * Computes available future dates based on the cook's schedule,
 * validates cart items against a scheduled date's meal availability,
 * and determines the default "next available slot" for display.
 *
 * BR-336: Date picker shows only dates where the cook has at least one available schedule entry.
 * BR-337: Unavailable dates (no schedule entries or all marked unavailable) are disabled.
 * BR-338: Maximum scheduling window is 14 days from today.
 * BR-339: Past dates and today are not selectable for scheduling.
 * BR-341: Cart items are validated against the scheduled date's meal availability.
 * BR-342: If a cart item is not available on the scheduled date, a warning is shown.
 */
class OrderSchedulingService
{
    /**
     * Maximum scheduling window in days from today (BR-338).
     */
    public const MAX_SCHEDULING_DAYS = 14;

    /**
     * Get available dates for scheduling within the next 14 days.
     *
     * BR-336: Only dates where the cook has at least one available schedule entry.
     * BR-337: Unavailable dates are excluded.
     * BR-338: Maximum 14 days from today.
     * BR-339: Today is excluded (use next available slot for today).
     *
     * @return array<string, array{date: string, day_of_week: string, day_label: string, available: bool}>
     */
    public function getAvailableDates(int $tenantId): array
    {
        // Get all available schedule days for this tenant
        $availableDays = CookSchedule::query()
            ->forTenant($tenantId)
            ->available()
            ->pluck('day_of_week')
            ->unique()
            ->toArray();

        $today = Carbon::now('Africa/Douala')->startOfDay();
        $dates = [];

        // Check each day in the next 14 days, starting from tomorrow (BR-339)
        for ($i = 1; $i <= self::MAX_SCHEDULING_DAYS; $i++) {
            $date = $today->copy()->addDays($i);
            $dayOfWeek = strtolower($date->format('l')); // e.g. "monday"

            $dates[$date->format('Y-m-d')] = [
                'date' => $date->format('Y-m-d'),
                'day_of_week' => $dayOfWeek,
                'day_label' => __(CookSchedule::DAY_LABELS[$dayOfWeek] ?? ucfirst($dayOfWeek)),
                'display_date' => $date->format('D, M j'), // e.g. "Mon, Feb 24"
                'full_date' => $date->format('l, F j, Y'), // e.g. "Monday, February 24, 2026"
                'available' => in_array($dayOfWeek, $availableDays, true),
            ];
        }

        return $dates;
    }

    /**
     * Check if there are ANY available dates for scheduling.
     *
     * Edge case: Cook has no available dates in the next 14 days → hide "Schedule for later".
     */
    public function hasAvailableDates(int $tenantId): bool
    {
        $dates = $this->getAvailableDates($tenantId);

        foreach ($dates as $date) {
            if ($date['available']) {
                return true;
            }
        }

        return false;
    }

    /**
     * Validate that a given date is valid for scheduling.
     *
     * BR-337: Date must have at least one available schedule entry.
     * BR-338: Date must be within 14 days from today.
     * BR-339: Date must not be today or in the past.
     *
     * @return array{valid: bool, error: string|null}
     */
    public function validateScheduledDate(int $tenantId, string $dateStr): array
    {
        try {
            $date = Carbon::createFromFormat('Y-m-d', $dateStr, 'Africa/Douala');
        } catch (\Exception) {
            return [
                'valid' => false,
                'error' => __('Invalid date format.'),
            ];
        }

        $today = Carbon::now('Africa/Douala')->startOfDay();
        $maxDate = $today->copy()->addDays(self::MAX_SCHEDULING_DAYS);

        // BR-339: No today or past dates
        if ($date->lte($today)) {
            return [
                'valid' => false,
                'error' => __('Please select a future date.'),
            ];
        }

        // BR-338: No dates beyond 14 days
        if ($date->gt($maxDate)) {
            return [
                'valid' => false,
                'error' => __('Scheduling is only available up to 14 days in advance.'),
            ];
        }

        // BR-337: Date must have available schedule entries
        $dayOfWeek = strtolower($date->format('l'));
        $hasSchedule = CookSchedule::query()
            ->forTenant($tenantId)
            ->forDay($dayOfWeek)
            ->available()
            ->exists();

        if (! $hasSchedule) {
            return [
                'valid' => false,
                'error' => __('The selected date is not available for ordering.'),
            ];
        }

        return ['valid' => true, 'error' => null];
    }

    /**
     * Validate cart items against the scheduled date's meal availability.
     *
     * BR-341: Cart items are validated against the scheduled date's meal availability.
     * BR-342: If a cart item is not available on the scheduled date, a warning is shown.
     *
     * A meal is "unavailable" on a date if:
     * - It has meal schedule overrides (MealSchedule entries), AND
     * - None of those overrides are available for the day of week of the scheduled date.
     *
     * If a meal has NO overrides, it follows the cook's default schedule (already valid for that date).
     *
     * @param  array  $cartItems  Items from CartService (keyed by component_id)
     * @return array<int, array{meal_id: int, meal_name: string, reason: string}>
     */
    public function getUnavailableCartItems(int $tenantId, string $dateStr, array $cartItems): array
    {
        if (empty($cartItems)) {
            return [];
        }

        try {
            $date = Carbon::createFromFormat('Y-m-d', $dateStr, 'Africa/Douala');
        } catch (\Exception) {
            return [];
        }

        $dayOfWeek = strtolower($date->format('l'));

        // Get unique meal IDs from cart
        $mealIds = collect($cartItems)
            ->pluck('meal_id')
            ->unique()
            ->filter()
            ->values()
            ->toArray();

        if (empty($mealIds)) {
            return [];
        }

        // Find meals that HAVE schedule overrides
        $mealsWithOverrides = MealSchedule::query()
            ->forTenant($tenantId)
            ->whereIn('meal_id', $mealIds)
            ->pluck('meal_id')
            ->unique()
            ->toArray();

        if (empty($mealsWithOverrides)) {
            // No meals have overrides → all follow cook's default schedule (already validated)
            return [];
        }

        // For meals with overrides, check if they are available on the scheduled day
        $unavailable = [];

        foreach ($mealsWithOverrides as $mealId) {
            $hasAvailableOverrideForDay = MealSchedule::query()
                ->forTenant($tenantId)
                ->forMeal($mealId)
                ->forDay($dayOfWeek)
                ->available()
                ->exists();

            if (! $hasAvailableOverrideForDay) {
                // Check if this meal has ANY override for this day at all (even unavailable)
                $hasOverrideForDay = MealSchedule::query()
                    ->forTenant($tenantId)
                    ->forMeal($mealId)
                    ->forDay($dayOfWeek)
                    ->exists();

                // If no override exists for this day, the meal is not available (override removes default)
                $meal = Meal::find($mealId);
                $locale = app()->getLocale();
                $mealName = $meal ? ($meal->{'name_'.$locale} ?? $meal->name_en) : __('Unknown meal');

                $unavailable[] = [
                    'meal_id' => $mealId,
                    'meal_name' => $mealName,
                    'reason' => $hasOverrideForDay
                        ? __(':meal is not available on :day.', ['meal' => $mealName, 'day' => __(CookSchedule::DAY_LABELS[$dayOfWeek] ?? ucfirst($dayOfWeek))])
                        : __(':meal is not scheduled for :day.', ['meal' => $mealName, 'day' => __(CookSchedule::DAY_LABELS[$dayOfWeek] ?? ucfirst($dayOfWeek))]),
                ];
            }
        }

        return $unavailable;
    }

    /**
     * Get the next available slot text for display.
     *
     * BR-335: Default behavior is ordering for the next available slot.
     * This returns a human-readable description of when the next slot is.
     *
     * @return array{text: string, day_label: string|null, date: string|null}
     */
    public function getNextAvailableSlot(int $tenantId): array
    {
        $dates = $this->getAvailableDates($tenantId);

        foreach ($dates as $date) {
            if ($date['available']) {
                return [
                    'text' => __('Next available: :day', ['day' => $date['display_date']]),
                    'day_label' => $date['day_label'],
                    'date' => $date['date'],
                ];
            }
        }

        return [
            'text' => __('No available slots in the next 14 days'),
            'day_label' => null,
            'date' => null,
        ];
    }

    /**
     * Format a Y-m-d date string for display.
     */
    public function formatScheduledDate(string $dateStr): string
    {
        try {
            $date = Carbon::createFromFormat('Y-m-d', $dateStr, 'Africa/Douala');

            return $date->format('l, F j, Y');
        } catch (\Exception) {
            return $dateStr;
        }
    }
}
