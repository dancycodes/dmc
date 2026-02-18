<?php

namespace App\Http\Controllers\Cook;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cook\StoreMealRequest;
use App\Http\Requests\Cook\UpdateMealRequest;
use App\Services\CookScheduleService;
use App\Services\MealImageService;
use App\Services\MealLocationOverrideService;
use App\Services\MealScheduleService;
use App\Services\MealService;
use Illuminate\Http\Request;

class MealController extends Controller
{
    /**
     * Display the meal list page.
     *
     * F-116: Meal List View (Cook Dashboard) - stub for now.
     * BR-194: Only users with can-manage-meals permission.
     */
    public function index(Request $request): mixed
    {
        $user = $request->user();
        $tenant = tenant();

        // BR-194: Permission check
        if (! $user->can('can-manage-meals')) {
            abort(403);
        }

        $meals = $tenant->meals()
            ->orderBy('position')
            ->orderByDesc('created_at')
            ->get();

        return gale()->view('cook.meals.index', [
            'meals' => $meals,
        ], web: true);
    }

    /**
     * Show the meal creation form.
     *
     * F-108: Meal Creation Form
     * BR-194: Only users with can-manage-meals permission.
     */
    public function create(Request $request): mixed
    {
        $user = $request->user();

        // BR-194: Permission check
        if (! $user->can('can-manage-meals')) {
            abort(403);
        }

        return gale()->view('cook.meals.create', web: true);
    }

    /**
     * Store a new meal.
     *
     * F-108: Meal Creation Form
     * BR-187: Meal name required in both EN and FR
     * BR-188: Meal description required in both EN and FR
     * BR-189: Meal name unique within tenant per language
     * BR-190: New meals default to "draft" status
     * BR-191: New meals default to "available" availability
     * BR-193: Meals are tenant-scoped
     * BR-194: Only users with can-manage-meals permission
     * BR-195: Meal creation is logged via Spatie Activitylog
     */
    public function store(Request $request, MealService $mealService): mixed
    {
        $user = $request->user();
        $tenant = tenant();

        // BR-194: Permission check
        if (! $user->can('can-manage-meals')) {
            abort(403);
        }

        // Dual Gale/HTTP validation pattern
        if ($request->isGale()) {
            $validated = $request->validateState([
                'name_en' => ['required', 'string', 'max:150'],
                'name_fr' => ['required', 'string', 'max:150'],
                'description_en' => ['required', 'string', 'max:2000'],
                'description_fr' => ['required', 'string', 'max:2000'],
            ], [
                'name_en.required' => __('Meal name is required in English.'),
                'name_en.max' => __('Meal name must not exceed :max characters.'),
                'name_fr.required' => __('Meal name is required in French.'),
                'name_fr.max' => __('Meal name must not exceed :max characters.'),
                'description_en.required' => __('Meal description is required in English.'),
                'description_en.max' => __('Meal description must not exceed :max characters.'),
                'description_fr.required' => __('Meal description is required in French.'),
                'description_fr.max' => __('Meal description must not exceed :max characters.'),
            ]);
        } else {
            $formRequest = app(StoreMealRequest::class);
            $validated = $formRequest->validated();
        }

        // Use MealService for business logic
        $result = $mealService->createMeal($tenant, $validated);

        if (! $result['success']) {
            // BR-189: Uniqueness violation
            $field = $result['field'] ?? 'name_en';

            if ($request->isGale()) {
                return gale()->messages([
                    $field => $result['error'],
                ]);
            }

            return redirect()->back()
                ->withErrors([$field => $result['error']])
                ->withInput();
        }

        $meal = $result['meal'];

        // BR-195: Activity logging
        activity('meals')
            ->performedOn($meal)
            ->causedBy($user)
            ->withProperties([
                'action' => 'meal_created',
                'name_en' => $meal->name_en,
                'name_fr' => $meal->name_fr,
                'status' => $meal->status,
                'tenant_id' => $tenant->id,
            ])
            ->log('Meal created');

        // Redirect to meal edit page (F-110 stub for now — redirect to meal list)
        $redirectUrl = url('/dashboard/meals/'.$meal->id.'/edit');

        if ($request->isGale()) {
            return gale()
                ->redirect($redirectUrl)
                ->with('success', __('Meal created.'));
        }

        return redirect($redirectUrl)
            ->with('success', __('Meal created.'));
    }

    /**
     * Show the meal edit form.
     *
     * F-110: Meal Edit.
     * F-096: Includes location override data.
     * F-106: Includes schedule override data.
     * F-109: Includes meal image data.
     * BR-215: Only users with can-manage-meals permission.
     */
    public function edit(
        Request $request,
        int $mealId,
        MealLocationOverrideService $overrideService,
        MealScheduleService $mealScheduleService,
        CookScheduleService $cookScheduleService,
        MealImageService $imageService,
    ): mixed {
        $user = $request->user();
        $tenant = tenant();

        // BR-194: Permission check
        if (! $user->can('can-manage-meals')) {
            abort(403);
        }

        $meal = $tenant->meals()->findOrFail($mealId);

        // F-096: Location override data
        $canManageLocations = $user->can('can-manage-delivery-areas');
        $locationData = $canManageLocations
            ? $overrideService->getLocationOverrideData($tenant, $meal)
            : null;

        // F-106: Schedule override data
        $canManageSchedules = $user->can('can-manage-schedules');
        $scheduleData = null;
        if ($canManageSchedules) {
            $hasCustomSchedule = $mealScheduleService->hasCustomSchedule($meal);
            $scheduleData = [
                'hasCustomSchedule' => $hasCustomSchedule,
                'daysOfWeek' => \App\Models\MealSchedule::DAYS_OF_WEEK,
                'dayLabels' => \App\Models\MealSchedule::DAY_LABELS,
                'maxPerDay' => \App\Models\MealSchedule::MAX_ENTRIES_PER_DAY,
            ];

            if ($hasCustomSchedule) {
                $scheduleData['schedulesByDay'] = $mealScheduleService->getSchedulesByDay($meal);
                $scheduleData['summary'] = $mealScheduleService->getScheduleSummary($meal);
            } else {
                $scheduleData['cookSchedulesByDay'] = $cookScheduleService->getSchedulesByDay($tenant);
                $scheduleData['cookSummary'] = $cookScheduleService->getScheduleSummary($tenant);
            }
        }

        // F-109: Meal image data
        $canManageMeals = $user->can('can-manage-meals');
        $mealImages = $imageService->getImagesData($meal);
        $mealImageCount = $imageService->getImageCount($meal);

        return gale()->view('cook.meals.edit', [
            'meal' => $meal,
            'canManageLocations' => $canManageLocations,
            'locationData' => $locationData,
            'canManageSchedules' => $canManageSchedules,
            'scheduleData' => $scheduleData,
            'canManageMeals' => $canManageMeals,
            'mealImages' => $mealImages,
            'mealImageCount' => $mealImageCount,
        ], web: true);
    }

    /**
     * Update a meal's basic info.
     *
     * F-110: Meal Edit
     * BR-210: Meal name required in both EN and FR
     * BR-211: Meal description required in both EN and FR
     * BR-212: Meal name unique within tenant per language
     * BR-213: Name max 150 characters per language
     * BR-214: Description max 2000 characters per language
     * BR-215: Only users with can-manage-meals permission
     * BR-216: Edits logged via Spatie Activitylog with old and new values
     * BR-217: Editing does not change status or availability
     */
    public function update(Request $request, int $mealId, MealService $mealService): mixed
    {
        $user = $request->user();
        $tenant = tenant();

        // BR-215: Permission check
        if (! $user->can('can-manage-meals')) {
            abort(403);
        }

        $meal = $tenant->meals()->findOrFail($mealId);

        // Dual Gale/HTTP validation pattern
        if ($request->isGale()) {
            $validated = $request->validateState([
                'name_en' => ['required', 'string', 'max:150'],
                'name_fr' => ['required', 'string', 'max:150'],
                'description_en' => ['required', 'string', 'max:2000'],
                'description_fr' => ['required', 'string', 'max:2000'],
            ], [
                'name_en.required' => __('Meal name is required in English.'),
                'name_en.max' => __('Meal name must not exceed :max characters.'),
                'name_fr.required' => __('Meal name is required in French.'),
                'name_fr.max' => __('Meal name must not exceed :max characters.'),
                'description_en.required' => __('Meal description is required in English.'),
                'description_en.max' => __('Meal description must not exceed :max characters.'),
                'description_fr.required' => __('Meal description is required in French.'),
                'description_fr.max' => __('Meal description must not exceed :max characters.'),
            ]);
        } else {
            $formRequest = app(UpdateMealRequest::class);
            $validated = $formRequest->validated();
        }

        // Use MealService for business logic (BR-212 uniqueness + BR-217 no status change)
        $result = $mealService->updateMeal($meal, $validated);

        if (! $result['success']) {
            // BR-212: Uniqueness violation
            $field = $result['field'] ?? 'name_en';

            if ($request->isGale()) {
                return gale()->messages([
                    $field => $result['error'],
                ]);
            }

            return redirect()->back()
                ->withErrors([$field => $result['error']])
                ->withInput();
        }

        // BR-216: Activity logging with old and new values
        $changes = $result['changes'] ?? [];
        if (! empty($changes)) {
            $oldValues = [];
            $newValues = [];

            foreach ($changes as $field => $change) {
                $oldValues[$field] = $change['old'];
                $newValues[$field] = $change['new'];
            }

            activity('meals')
                ->performedOn($meal)
                ->causedBy($user)
                ->withProperties([
                    'action' => 'meal_updated',
                    'old' => $oldValues,
                    'new' => $newValues,
                    'tenant_id' => $tenant->id,
                ])
                ->log('Meal updated');
        }

        $redirectUrl = url('/dashboard/meals/'.$meal->id.'/edit');

        if ($request->isGale()) {
            return gale()
                ->redirect($redirectUrl)
                ->with('success', __('Meal updated.'));
        }

        return redirect($redirectUrl)
            ->with('success', __('Meal updated.'));
    }
}
