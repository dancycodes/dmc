<?php

namespace App\Http\Controllers\Cook;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cook\StoreMealScheduleRequest;
use App\Models\Meal;
use App\Models\MealSchedule;
use App\Rules\ValidTimeFormat;
use App\Services\CookScheduleService;
use App\Services\MealScheduleService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * F-106: Meal Schedule Override
 *
 * Manages meal-specific schedule overrides. Provides the ability to
 * toggle between the cook's default schedule and a custom schedule
 * for individual meals, plus CRUD operations on meal schedule entries.
 *
 * BR-171: Requires both can-manage-meals and can-manage-schedules permissions.
 */
class MealScheduleController extends Controller
{
    /**
     * Get the schedule override data for a meal (used in meal edit page).
     *
     * Returns JSON data for the schedule section of the meal edit page.
     * BR-171: Permission check
     */
    public function getData(
        Request $request,
        int $mealId,
        MealScheduleService $mealScheduleService,
        CookScheduleService $cookScheduleService,
    ): mixed {
        $user = $request->user();
        $tenant = tenant();

        if (! $user->can('can-manage-meals') || ! $user->can('can-manage-schedules')) {
            abort(403);
        }

        $meal = $tenant->meals()->findOrFail($mealId);
        $hasCustomSchedule = $mealScheduleService->hasCustomSchedule($meal);

        $data = [
            'meal' => $meal,
            'hasCustomSchedule' => $hasCustomSchedule,
            'daysOfWeek' => MealSchedule::DAYS_OF_WEEK,
            'dayLabels' => MealSchedule::DAY_LABELS,
            'maxPerDay' => MealSchedule::MAX_ENTRIES_PER_DAY,
        ];

        if ($hasCustomSchedule) {
            $data['schedulesByDay'] = $mealScheduleService->getSchedulesByDay($meal);
            $data['summary'] = $mealScheduleService->getScheduleSummary($meal);
        } else {
            $data['cookSchedulesByDay'] = $cookScheduleService->getSchedulesByDay($tenant);
            $data['cookSummary'] = $cookScheduleService->getScheduleSummary($tenant);
        }

        return gale()->view('cook.meals._schedule-override', $data, web: true);
    }

    /**
     * Store a new meal schedule entry.
     *
     * BR-169: Tenant-scoped and meal-scoped
     * BR-170: Logged via Spatie Activitylog
     * BR-171: Permission check
     */
    public function store(
        Request $request,
        int $mealId,
        MealScheduleService $mealScheduleService,
    ): mixed {
        $user = $request->user();
        $tenant = tenant();

        if (! $user->can('can-manage-meals') || ! $user->can('can-manage-schedules')) {
            abort(403);
        }

        $meal = $tenant->meals()->findOrFail($mealId);

        if ($request->isGale()) {
            $validated = $request->validateState([
                'day_of_week' => ['required', 'string', Rule::in(MealSchedule::DAYS_OF_WEEK)],
                'is_available' => ['required'],
                'label' => ['nullable', 'string', 'max:100'],
            ], [
                'day_of_week.required' => __('Please select a day of the week.'),
                'day_of_week.in' => __('Please select a valid day of the week.'),
                'is_available.required' => __('Availability status is required.'),
                'label.max' => __('Label must not exceed 100 characters.'),
            ]);
        } else {
            $formRequest = app(StoreMealScheduleRequest::class);
            $validated = $formRequest->validated();
        }

        $isAvailable = filter_var($validated['is_available'], FILTER_VALIDATE_BOOLEAN);

        $result = $mealScheduleService->createScheduleEntry(
            $tenant,
            $meal,
            $validated['day_of_week'],
            $isAvailable,
            $validated['label'] ?? null,
        );

        if (! $result['success']) {
            if ($request->isGale()) {
                return gale()->messages(['day_of_week' => $result['error']]);
            }

            return redirect()->back()->withErrors(['day_of_week' => $result['error']])->withInput();
        }

        // BR-170: Activity logging
        activity('meal_schedules')
            ->performedOn($result['schedule'])
            ->causedBy($user)
            ->withProperties([
                'action' => 'meal_schedule_created',
                'meal_id' => $meal->id,
                'meal_name' => $meal->name,
                'day_of_week' => $validated['day_of_week'],
                'is_available' => $isAvailable,
                'label' => $result['schedule']->display_label,
                'tenant_id' => $tenant->id,
            ])
            ->log('Meal schedule entry created');

        $redirectUrl = url('/dashboard/meals/'.$meal->id.'/edit');

        if ($request->isGale()) {
            return gale()
                ->redirect($redirectUrl)
                ->with('success', __('Schedule entry created successfully.'));
        }

        return redirect($redirectUrl)->with('success', __('Schedule entry created successfully.'));
    }

    /**
     * Update the order time interval for a meal schedule entry.
     *
     * BR-166: Same rules as cook schedule order interval (F-099)
     * BR-170: Logged via Spatie Activitylog
     */
    public function updateOrderInterval(
        Request $request,
        int $mealId,
        MealSchedule $mealSchedule,
        MealScheduleService $mealScheduleService,
    ): mixed {
        $user = $request->user();
        $tenant = tenant();

        if (! $user->can('can-manage-meals') || ! $user->can('can-manage-schedules')) {
            abort(403);
        }

        $meal = $tenant->meals()->findOrFail($mealId);

        if ($mealSchedule->meal_id !== $meal->id || $mealSchedule->tenant_id !== $tenant->id) {
            abort(404);
        }

        if ($request->isGale()) {
            $validated = $request->validateState([
                'order_start_time' => ['required', new ValidTimeFormat],
                'order_start_day_offset' => ['required', 'integer', 'min:0', 'max:'.MealSchedule::MAX_START_DAY_OFFSET],
                'order_end_time' => ['required', new ValidTimeFormat],
                'order_end_day_offset' => ['required', 'integer', 'min:0', 'max:'.MealSchedule::MAX_END_DAY_OFFSET],
            ], [
                'order_start_time.required' => __('Start time is required.'),
                'order_start_day_offset.required' => __('Start day offset is required.'),
                'order_end_time.required' => __('End time is required.'),
                'order_end_day_offset.required' => __('End day offset is required.'),
            ]);
        } else {
            $validated = $request->validate([
                'order_start_time' => ['required', new ValidTimeFormat],
                'order_start_day_offset' => ['required', 'integer', 'min:0', 'max:'.MealSchedule::MAX_START_DAY_OFFSET],
                'order_end_time' => ['required', new ValidTimeFormat],
                'order_end_day_offset' => ['required', 'integer', 'min:0', 'max:'.MealSchedule::MAX_END_DAY_OFFSET],
            ]);
        }

        $oldValues = [
            'order_start_time' => $mealSchedule->order_start_time,
            'order_start_day_offset' => $mealSchedule->order_start_day_offset,
            'order_end_time' => $mealSchedule->order_end_time,
            'order_end_day_offset' => $mealSchedule->order_end_day_offset,
        ];

        $result = $mealScheduleService->updateOrderInterval(
            $mealSchedule,
            $validated['order_start_time'],
            (int) $validated['order_start_day_offset'],
            $validated['order_end_time'],
            (int) $validated['order_end_day_offset'],
        );

        if (! $result['success']) {
            if ($request->isGale()) {
                return gale()->messages(['order_start_time' => $result['error']]);
            }

            return redirect()->back()->withErrors(['order_start_time' => $result['error']])->withInput();
        }

        activity('meal_schedules')
            ->performedOn($result['schedule'])
            ->causedBy($user)
            ->withProperties([
                'action' => 'meal_order_interval_configured',
                'meal_id' => $meal->id,
                'meal_name' => $meal->name,
                'day_of_week' => $mealSchedule->day_of_week,
                'old' => $oldValues,
                'new' => [
                    'order_start_time' => $validated['order_start_time'],
                    'order_start_day_offset' => (int) $validated['order_start_day_offset'],
                    'order_end_time' => $validated['order_end_time'],
                    'order_end_day_offset' => (int) $validated['order_end_day_offset'],
                ],
                'tenant_id' => $tenant->id,
            ])
            ->log('Meal order interval configured');

        $redirectUrl = url('/dashboard/meals/'.$meal->id.'/edit');

        if ($request->isGale()) {
            return gale()
                ->redirect($redirectUrl)
                ->with('success', __('Order interval configured successfully.'));
        }

        return redirect($redirectUrl)->with('success', __('Order interval configured successfully.'));
    }

    /**
     * Update the delivery/pickup time intervals for a meal schedule entry.
     *
     * BR-166: Same rules as cook schedule delivery/pickup interval (F-100)
     * BR-170: Logged via Spatie Activitylog
     */
    public function updateDeliveryPickupInterval(
        Request $request,
        int $mealId,
        MealSchedule $mealSchedule,
        MealScheduleService $mealScheduleService,
    ): mixed {
        $user = $request->user();
        $tenant = tenant();

        if (! $user->can('can-manage-meals') || ! $user->can('can-manage-schedules')) {
            abort(403);
        }

        $meal = $tenant->meals()->findOrFail($mealId);

        if ($mealSchedule->meal_id !== $meal->id || $mealSchedule->tenant_id !== $tenant->id) {
            abort(404);
        }

        if ($request->isGale()) {
            $validated = $request->validateState([
                'delivery_enabled' => ['required'],
                'delivery_start_time' => ['nullable', new ValidTimeFormat],
                'delivery_end_time' => ['nullable', new ValidTimeFormat],
                'pickup_enabled' => ['required'],
                'pickup_start_time' => ['nullable', new ValidTimeFormat],
                'pickup_end_time' => ['nullable', new ValidTimeFormat],
            ], [
                'delivery_enabled.required' => __('Delivery status is required.'),
                'pickup_enabled.required' => __('Pickup status is required.'),
            ]);
        } else {
            $validated = $request->validate([
                'delivery_enabled' => ['required'],
                'delivery_start_time' => ['nullable', new ValidTimeFormat],
                'delivery_end_time' => ['nullable', new ValidTimeFormat],
                'pickup_enabled' => ['required'],
                'pickup_start_time' => ['nullable', new ValidTimeFormat],
                'pickup_end_time' => ['nullable', new ValidTimeFormat],
            ]);
        }

        $deliveryEnabled = filter_var($validated['delivery_enabled'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $pickupEnabled = filter_var($validated['pickup_enabled'] ?? false, FILTER_VALIDATE_BOOLEAN);

        $oldValues = [
            'delivery_enabled' => $mealSchedule->delivery_enabled,
            'delivery_start_time' => $mealSchedule->delivery_start_time,
            'delivery_end_time' => $mealSchedule->delivery_end_time,
            'pickup_enabled' => $mealSchedule->pickup_enabled,
            'pickup_start_time' => $mealSchedule->pickup_start_time,
            'pickup_end_time' => $mealSchedule->pickup_end_time,
        ];

        $result = $mealScheduleService->updateDeliveryPickupInterval(
            $mealSchedule,
            $deliveryEnabled,
            $deliveryEnabled ? ($validated['delivery_start_time'] ?? null) : null,
            $deliveryEnabled ? ($validated['delivery_end_time'] ?? null) : null,
            $pickupEnabled,
            $pickupEnabled ? ($validated['pickup_start_time'] ?? null) : null,
            $pickupEnabled ? ($validated['pickup_end_time'] ?? null) : null,
        );

        if (! $result['success']) {
            $field = $result['field'] ?? 'delivery_enabled';

            if ($request->isGale()) {
                return gale()->messages([$field => $result['error']]);
            }

            return redirect()->back()->withErrors([$field => $result['error']])->withInput();
        }

        activity('meal_schedules')
            ->performedOn($result['schedule'])
            ->causedBy($user)
            ->withProperties([
                'action' => 'meal_delivery_pickup_interval_configured',
                'meal_id' => $meal->id,
                'meal_name' => $meal->name,
                'day_of_week' => $mealSchedule->day_of_week,
                'old' => $oldValues,
                'new' => [
                    'delivery_enabled' => $deliveryEnabled,
                    'delivery_start_time' => $deliveryEnabled ? ($validated['delivery_start_time'] ?? null) : null,
                    'delivery_end_time' => $deliveryEnabled ? ($validated['delivery_end_time'] ?? null) : null,
                    'pickup_enabled' => $pickupEnabled,
                    'pickup_start_time' => $pickupEnabled ? ($validated['pickup_start_time'] ?? null) : null,
                    'pickup_end_time' => $pickupEnabled ? ($validated['pickup_end_time'] ?? null) : null,
                ],
                'tenant_id' => $tenant->id,
            ])
            ->log('Meal delivery/pickup interval configured');

        $redirectUrl = url('/dashboard/meals/'.$meal->id.'/edit');

        if ($request->isGale()) {
            return gale()
                ->redirect($redirectUrl)
                ->with('success', __('Delivery/pickup intervals configured successfully.'));
        }

        return redirect($redirectUrl)->with('success', __('Delivery/pickup intervals configured successfully.'));
    }

    /**
     * Revert a meal to the cook's default schedule.
     *
     * BR-167: Deletes all meal-specific schedule entries
     * BR-168: Confirmation dialog shown before this action (handled on frontend)
     * BR-170: Logged via Spatie Activitylog
     */
    public function revert(
        Request $request,
        int $mealId,
        MealScheduleService $mealScheduleService,
    ): mixed {
        $user = $request->user();
        $tenant = tenant();

        if (! $user->can('can-manage-meals') || ! $user->can('can-manage-schedules')) {
            abort(403);
        }

        $meal = $tenant->meals()->findOrFail($mealId);

        $result = $mealScheduleService->revertToDefaultSchedule($meal);

        activity('meal_schedules')
            ->performedOn($meal)
            ->causedBy($user)
            ->withProperties([
                'action' => 'meal_schedule_reverted',
                'meal_id' => $meal->id,
                'meal_name' => $meal->name,
                'deleted_entries' => $result['deleted_count'],
                'tenant_id' => $tenant->id,
            ])
            ->log('Meal schedule reverted to cook default');

        $redirectUrl = url('/dashboard/meals/'.$meal->id.'/edit');

        if ($request->isGale()) {
            return gale()
                ->redirect($redirectUrl)
                ->with('success', __('Meal schedule reverted to cook\'s default schedule.'));
        }

        return redirect($redirectUrl)->with('success', __('Meal schedule reverted to cook\'s default schedule.'));
    }
}
