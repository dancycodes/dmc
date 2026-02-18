<?php

namespace App\Http\Controllers\Cook;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cook\StoreCookScheduleRequest;
use App\Http\Requests\Cook\UpdateDeliveryPickupIntervalRequest;
use App\Http\Requests\Cook\UpdateOrderIntervalRequest;
use App\Models\CookSchedule;
use App\Services\CookScheduleService;
use Illuminate\Http\Request;

/**
 * F-098: Cook Day Schedule Creation
 * F-099: Order Time Interval Configuration
 * F-100: Delivery/Pickup Time Interval Configuration
 *
 * Manages day schedule entries for cooks. Schedule entries define which
 * days a cook operates and support multiple slots per day (e.g., Lunch, Dinner).
 * Also handles order time interval and delivery/pickup interval configuration.
 */
class CookScheduleController extends Controller
{
    /**
     * Display the schedule management page.
     *
     * BR-103: Only users with can-manage-schedules permission
     * BR-102: Schedule entries are tenant-scoped
     */
    public function index(Request $request, CookScheduleService $scheduleService): mixed
    {
        $user = $request->user();
        $tenant = tenant();

        // BR-103: Permission check
        if (! $user->can('can-manage-schedules')) {
            abort(403);
        }

        $schedulesByDay = $scheduleService->getSchedulesByDay($tenant);
        $summary = $scheduleService->getScheduleSummary($tenant);

        return gale()->view('cook.schedule.index', [
            'schedulesByDay' => $schedulesByDay,
            'summary' => $summary,
            'daysOfWeek' => CookSchedule::DAYS_OF_WEEK,
            'dayLabels' => CookSchedule::DAY_LABELS,
            'maxPerDay' => CookSchedule::MAX_ENTRIES_PER_DAY,
        ], web: true);
    }

    /**
     * Store a new schedule entry.
     *
     * BR-098: Each entry belongs to a single day of the week
     * BR-099: Entry has an availability flag
     * BR-100: Maximum entries per day enforced (default 3)
     * BR-102: Tenant-scoped
     * BR-103: Only users with can-manage-schedules permission
     * BR-104: Creation logged via Spatie Activitylog
     * BR-105: Label defaults to "Slot N" based on position
     */
    public function store(Request $request, CookScheduleService $scheduleService): mixed
    {
        $user = $request->user();
        $tenant = tenant();

        // BR-103: Permission check
        if (! $user->can('can-manage-schedules')) {
            abort(403);
        }

        // Dual Gale/HTTP validation pattern
        if ($request->isGale()) {
            $validated = $request->validateState([
                'day_of_week' => ['required', 'string', \Illuminate\Validation\Rule::in(CookSchedule::DAYS_OF_WEEK)],
                'is_available' => ['required'],
                'label' => ['nullable', 'string', 'max:100'],
            ], [
                'day_of_week.required' => __('Please select a day of the week.'),
                'day_of_week.in' => __('Please select a valid day of the week.'),
                'is_available.required' => __('Availability status is required.'),
                'label.max' => __('Label must not exceed 100 characters.'),
            ]);
        } else {
            $formRequest = app(StoreCookScheduleRequest::class);
            $validated = $formRequest->validated();
        }

        // Normalize is_available to boolean
        $isAvailable = filter_var($validated['is_available'], FILTER_VALIDATE_BOOLEAN);

        $result = $scheduleService->createScheduleEntry(
            $tenant,
            $validated['day_of_week'],
            $isAvailable,
            $validated['label'] ?? null,
        );

        if (! $result['success']) {
            // BR-100: Per-day limit reached
            if ($request->isGale()) {
                return gale()->messages([
                    'day_of_week' => $result['error'],
                ]);
            }

            return redirect()->back()->withErrors(['day_of_week' => $result['error']])->withInput();
        }

        // BR-104: Activity logging
        activity('cook_schedules')
            ->performedOn($result['schedule'])
            ->causedBy($user)
            ->withProperties([
                'action' => 'schedule_created',
                'day_of_week' => $validated['day_of_week'],
                'is_available' => $isAvailable,
                'label' => $result['schedule']->display_label,
                'position' => $result['schedule']->position,
                'tenant_id' => $tenant->id,
            ])
            ->log('Schedule entry created');

        // Gale redirect with toast
        if ($request->isGale()) {
            return gale()
                ->redirect(url('/dashboard/schedule'))
                ->with('success', __('Schedule entry created successfully.'));
        }

        return redirect()->route('cook.schedule.index')
            ->with('success', __('Schedule entry created successfully.'));
    }

    /**
     * Update the order time interval for a schedule entry.
     *
     * F-099: Order Time Interval Configuration
     *
     * BR-106: Start = time + day offset (0-7)
     * BR-107: End = time + day offset (0-1)
     * BR-108: Start must be chronologically before end
     * BR-109: Time format is 24-hour (HH:MM)
     * BR-112: Only available entries can have intervals configured
     * BR-115: Changes logged via Spatie Activitylog
     */
    public function updateOrderInterval(
        Request $request,
        CookSchedule $cookSchedule,
        CookScheduleService $scheduleService,
    ): mixed {
        $user = $request->user();
        $tenant = tenant();

        // BR-103: Permission check
        if (! $user->can('can-manage-schedules')) {
            abort(403);
        }

        // Ensure schedule belongs to this tenant
        if ($cookSchedule->tenant_id !== $tenant->id) {
            abort(404);
        }

        // Dual Gale/HTTP validation pattern
        if ($request->isGale()) {
            $validated = $request->validateState([
                'order_start_time' => ['required', 'date_format:H:i'],
                'order_start_day_offset' => ['required', 'integer', 'min:0', 'max:'.CookSchedule::MAX_START_DAY_OFFSET],
                'order_end_time' => ['required', 'date_format:H:i'],
                'order_end_day_offset' => ['required', 'integer', 'min:0', 'max:'.CookSchedule::MAX_END_DAY_OFFSET],
            ], [
                'order_start_time.required' => __('Start time is required.'),
                'order_start_time.date_format' => __('Start time must be in HH:MM format (24-hour).'),
                'order_start_day_offset.required' => __('Start day offset is required.'),
                'order_start_day_offset.max' => __('Start day offset cannot exceed :max days before.', ['max' => CookSchedule::MAX_START_DAY_OFFSET]),
                'order_end_time.required' => __('End time is required.'),
                'order_end_time.date_format' => __('End time must be in HH:MM format (24-hour).'),
                'order_end_day_offset.required' => __('End day offset is required.'),
                'order_end_day_offset.max' => __('End day offset cannot exceed :max day before.', ['max' => CookSchedule::MAX_END_DAY_OFFSET]),
            ]);
        } else {
            $formRequest = app(UpdateOrderIntervalRequest::class);
            $validated = $formRequest->validated();
        }

        // Store old values for activity log
        $oldValues = [
            'order_start_time' => $cookSchedule->order_start_time,
            'order_start_day_offset' => $cookSchedule->order_start_day_offset,
            'order_end_time' => $cookSchedule->order_end_time,
            'order_end_day_offset' => $cookSchedule->order_end_day_offset,
        ];

        $result = $scheduleService->updateOrderInterval(
            $cookSchedule,
            $validated['order_start_time'],
            (int) $validated['order_start_day_offset'],
            $validated['order_end_time'],
            (int) $validated['order_end_day_offset'],
        );

        if (! $result['success']) {
            if ($request->isGale()) {
                return gale()->messages([
                    'order_start_time' => $result['error'],
                ]);
            }

            return redirect()->back()->withErrors(['order_start_time' => $result['error']])->withInput();
        }

        // BR-115: Activity logging
        activity('cook_schedules')
            ->performedOn($result['schedule'])
            ->causedBy($user)
            ->withProperties([
                'action' => 'order_interval_configured',
                'day_of_week' => $cookSchedule->day_of_week,
                'label' => $cookSchedule->display_label,
                'old' => $oldValues,
                'new' => [
                    'order_start_time' => $validated['order_start_time'],
                    'order_start_day_offset' => (int) $validated['order_start_day_offset'],
                    'order_end_time' => $validated['order_end_time'],
                    'order_end_day_offset' => (int) $validated['order_end_day_offset'],
                ],
                'tenant_id' => $tenant->id,
            ])
            ->log('Order interval configured');

        if ($request->isGale()) {
            return gale()
                ->redirect(url('/dashboard/schedule'))
                ->with('success', __('Order interval configured successfully.'));
        }

        return redirect()->route('cook.schedule.index')
            ->with('success', __('Order interval configured successfully.'));
    }

    /**
     * Update the delivery/pickup time intervals for a schedule entry.
     *
     * F-100: Delivery/Pickup Time Interval Configuration
     *
     * BR-116: Both intervals on the open day (day offset 0)
     * BR-117: Delivery start >= order interval end time
     * BR-118: Pickup start >= order interval end time
     * BR-119: Delivery end > delivery start
     * BR-120: Pickup end > pickup start
     * BR-121: At least one must be enabled
     * BR-124: Order interval must be configured first
     * BR-126: Changes logged via Spatie Activitylog
     */
    public function updateDeliveryPickupInterval(
        Request $request,
        CookSchedule $cookSchedule,
        CookScheduleService $scheduleService,
    ): mixed {
        $user = $request->user();
        $tenant = tenant();

        // BR-103: Permission check
        if (! $user->can('can-manage-schedules')) {
            abort(403);
        }

        // Ensure schedule belongs to this tenant
        if ($cookSchedule->tenant_id !== $tenant->id) {
            abort(404);
        }

        // Dual Gale/HTTP validation pattern
        if ($request->isGale()) {
            $validated = $request->validateState([
                'delivery_enabled' => ['required'],
                'delivery_start_time' => ['nullable', 'date_format:H:i'],
                'delivery_end_time' => ['nullable', 'date_format:H:i'],
                'pickup_enabled' => ['required'],
                'pickup_start_time' => ['nullable', 'date_format:H:i'],
                'pickup_end_time' => ['nullable', 'date_format:H:i'],
            ], [
                'delivery_enabled.required' => __('Delivery status is required.'),
                'delivery_start_time.date_format' => __('Delivery start time must be in HH:MM format (24-hour).'),
                'delivery_end_time.date_format' => __('Delivery end time must be in HH:MM format (24-hour).'),
                'pickup_enabled.required' => __('Pickup status is required.'),
                'pickup_start_time.date_format' => __('Pickup start time must be in HH:MM format (24-hour).'),
                'pickup_end_time.date_format' => __('Pickup end time must be in HH:MM format (24-hour).'),
            ]);
        } else {
            $formRequest = app(UpdateDeliveryPickupIntervalRequest::class);
            $validated = $formRequest->validated();
        }

        // Normalize booleans
        $deliveryEnabled = filter_var($validated['delivery_enabled'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $pickupEnabled = filter_var($validated['pickup_enabled'] ?? false, FILTER_VALIDATE_BOOLEAN);

        // Store old values for activity log
        $oldValues = [
            'delivery_enabled' => $cookSchedule->delivery_enabled,
            'delivery_start_time' => $cookSchedule->delivery_start_time,
            'delivery_end_time' => $cookSchedule->delivery_end_time,
            'pickup_enabled' => $cookSchedule->pickup_enabled,
            'pickup_start_time' => $cookSchedule->pickup_start_time,
            'pickup_end_time' => $cookSchedule->pickup_end_time,
        ];

        $result = $scheduleService->updateDeliveryPickupInterval(
            $cookSchedule,
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
                return gale()->messages([
                    $field => $result['error'],
                ]);
            }

            return redirect()->back()->withErrors([$field => $result['error']])->withInput();
        }

        // BR-126: Activity logging
        activity('cook_schedules')
            ->performedOn($result['schedule'])
            ->causedBy($user)
            ->withProperties([
                'action' => 'delivery_pickup_interval_configured',
                'day_of_week' => $cookSchedule->day_of_week,
                'label' => $cookSchedule->display_label,
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
            ->log('Delivery/pickup interval configured');

        if ($request->isGale()) {
            return gale()
                ->redirect(url('/dashboard/schedule'))
                ->with('success', __('Delivery/pickup intervals configured successfully.'));
        }

        return redirect()->route('cook.schedule.index')
            ->with('success', __('Delivery/pickup intervals configured successfully.'));
    }
}
