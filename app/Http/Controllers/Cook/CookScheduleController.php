<?php

namespace App\Http\Controllers\Cook;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cook\StoreCookScheduleRequest;
use App\Models\CookSchedule;
use App\Services\CookScheduleService;
use Illuminate\Http\Request;

/**
 * F-098: Cook Day Schedule Creation
 *
 * Manages day schedule entries for cooks. Schedule entries define which
 * days a cook operates and support multiple slots per day (e.g., Lunch, Dinner).
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
}
