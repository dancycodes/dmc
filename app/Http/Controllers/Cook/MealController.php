<?php

namespace App\Http\Controllers\Cook;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cook\StoreMealRequest;
use App\Http\Requests\Cook\UpdateMealRequest;
use App\Models\Meal;
use App\Services\ComponentRequirementRuleService;
use App\Services\CookScheduleService;
use App\Services\MealComponentService;
use App\Services\MealImageService;
use App\Services\MealLocationOverrideService;
use App\Services\MealScheduleService;
use App\Services\MealService;
use App\Services\MealTagService;
use Illuminate\Http\Request;

class MealController extends Controller
{
    /**
     * Display the meal list page.
     *
     * F-116: Meal List View (Cook Dashboard)
     * BR-261: Tenant-scoped meals only.
     * BR-262: Soft-deleted meals excluded.
     * BR-263: Search matches against both name_en and name_fr.
     * BR-264: Status filter: All, Draft, Live.
     * BR-265: Availability filter: All, Available, Unavailable.
     * BR-266: Sort options: Name A-Z, Name Z-A, Newest, Oldest, Most Ordered.
     * BR-268: Only users with can-manage-meals permission.
     * BR-269: Component count and order count per meal.
     */
    public function index(Request $request, MealService $mealService): mixed
    {
        $user = $request->user();
        $tenant = tenant();

        // BR-268: Permission check
        if (! $user->can('can-manage-meals')) {
            abort(403);
        }

        $filters = [
            'search' => $request->input('search', ''),
            'status' => $request->input('status', ''),
            'availability' => $request->input('availability', ''),
            'sort' => $request->input('sort', 'newest'),
        ];

        $listData = $mealService->getMealListData($tenant, $filters);

        $data = [
            'meals' => $listData['meals'],
            'totalCount' => $listData['totalCount'],
            'draftCount' => $listData['draftCount'],
            'liveCount' => $listData['liveCount'],
            'availableCount' => $listData['availableCount'],
            'unavailableCount' => $listData['unavailableCount'],
            'search' => $filters['search'],
            'status' => $filters['status'],
            'availability' => $filters['availability'],
            'sort' => $filters['sort'],
        ];

        // Handle Gale navigate requests (search/filter/sort triggers)
        if ($request->isGaleNavigate('meal-list')) {
            return gale()->fragment('cook.meals.index', 'meal-list-content', $data);
        }

        return gale()->view('cook.meals.index', $data, web: true);
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
        MealService $mealService,
        MealLocationOverrideService $overrideService,
        MealScheduleService $mealScheduleService,
        CookScheduleService $cookScheduleService,
        MealImageService $imageService,
        MealTagService $mealTagService,
        MealComponentService $componentService,
        ComponentRequirementRuleService $ruleService,
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

        // F-111: Delete eligibility data
        $canDeleteInfo = $mealService->canDeleteMeal($meal);
        $completedOrders = $mealService->getCompletedOrderCount($meal);

        // F-114: Tag assignment data
        $tagData = $canManageMeals
            ? $mealTagService->getTagAssignmentData($tenant, $meal)
            : null;

        // F-118: Meal component data
        $componentData = $canManageMeals
            ? $componentService->getComponentsData($meal)
            : null;
        $availableUnits = $canManageMeals
            ? $componentService->getAvailableUnitsWithLabels($tenant)
            : [];

        // F-120: Compute delete eligibility per component for UI
        $componentDeleteInfo = [];
        // F-122: Compute requirement rules per component for UI
        $componentRulesData = [];
        if ($componentData && $componentData['count'] > 0) {
            foreach ($componentData['components'] as $comp) {
                $componentDeleteInfo[$comp->id] = $componentService->canDeleteComponent(
                    $comp,
                    $meal,
                    $componentData['count']
                );
                $componentRulesData[$comp->id] = [
                    'rules' => $ruleService->getRulesForComponent($comp),
                    'available_targets' => $ruleService->getAvailableTargets($comp),
                ];
            }
        }

        return gale()->view('cook.meals.edit', [
            'meal' => $meal,
            'canManageLocations' => $canManageLocations,
            'locationData' => $locationData,
            'canManageSchedules' => $canManageSchedules,
            'scheduleData' => $scheduleData,
            'canManageMeals' => $canManageMeals,
            'mealImages' => $mealImages,
            'mealImageCount' => $mealImageCount,
            'canDeleteInfo' => $canDeleteInfo,
            'completedOrders' => $completedOrders,
            'tagData' => $tagData,
            'componentData' => $componentData,
            'availableUnits' => $availableUnits,
            'componentDeleteInfo' => $componentDeleteInfo,
            'componentRulesData' => $componentRulesData,
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

    /**
     * Toggle a meal's status between draft and live.
     *
     * F-112: Meal Status Toggle (Draft/Live)
     * BR-227: A meal must have at least one component to go live
     * BR-231: Status toggle has immediate effect
     * BR-232: Status change is logged via Spatie Activitylog
     * BR-233: Only users with manage-meals permission
     * BR-234: Status enum values: draft, live
     */
    public function toggleStatus(Request $request, int $mealId, MealService $mealService): mixed
    {
        $user = $request->user();
        $tenant = tenant();

        // BR-233: Permission check
        if (! $user->can('can-manage-meals')) {
            abort(403);
        }

        $meal = $tenant->meals()->findOrFail($mealId);

        // Use MealService for business logic
        $result = $mealService->toggleStatus($meal);

        if (! $result['success']) {
            // BR-227: No components — block going live
            $referer = $request->header('Referer', '');
            $redirectUrl = str_contains($referer, '/meals/') && str_contains($referer, '/edit')
                ? url('/dashboard/meals/'.$meal->id.'/edit')
                : url('/dashboard/meals');

            if ($request->isGale()) {
                return gale()
                    ->redirect($redirectUrl)
                    ->with('error', $result['error']);
            }

            return redirect($redirectUrl)
                ->with('error', $result['error']);
        }

        // BR-232: Activity logging
        activity('meals')
            ->performedOn($meal)
            ->causedBy($user)
            ->withProperties([
                'action' => 'meal_status_toggled',
                'old_status' => $result['old_status'],
                'new_status' => $result['new_status'],
                'name_en' => $meal->name_en,
                'name_fr' => $meal->name_fr,
                'tenant_id' => $tenant->id,
            ])
            ->log('Meal status changed from '.$result['old_status'].' to '.$result['new_status']);

        // Toast message based on new status
        $mealName = $meal->name;
        $toastMessage = $result['new_status'] === Meal::STATUS_LIVE
            ? __(':name is now live.', ['name' => $mealName])
            : __(':name is now in draft.', ['name' => $mealName]);

        $referer = $request->header('Referer', '');
        $redirectUrl = str_contains($referer, '/meals/') && str_contains($referer, '/edit')
            ? url('/dashboard/meals/'.$meal->id.'/edit')
            : url('/dashboard/meals');

        if ($request->isGale()) {
            return gale()
                ->redirect($redirectUrl)
                ->with('success', $toastMessage);
        }

        return redirect($redirectUrl)
            ->with('success', $toastMessage);
    }

    /**
     * Toggle a meal's availability between available and unavailable.
     *
     * F-113: Meal Availability Toggle
     * BR-235: Availability is separate from status (draft/live)
     * BR-236: A live + unavailable meal is visible but cannot be ordered
     * BR-237: A live + available meal is fully orderable
     * BR-238: A draft meal's availability takes effect only when the meal goes live
     * BR-240: Availability toggle has immediate effect
     * BR-241: Availability changes are logged via Spatie Activitylog
     * BR-242: Only users with manage-meals permission
     * BR-243: Toggling availability does not affect pending or active orders
     */
    public function toggleAvailability(Request $request, int $mealId, MealService $mealService): mixed
    {
        $user = $request->user();
        $tenant = tenant();

        // BR-242: Permission check
        if (! $user->can('can-manage-meals')) {
            abort(403);
        }

        $meal = $tenant->meals()->findOrFail($mealId);

        // Use MealService for business logic
        $result = $mealService->toggleAvailability($meal);

        // BR-241: Activity logging
        activity('meals')
            ->performedOn($meal)
            ->causedBy($user)
            ->withProperties([
                'action' => 'meal_availability_toggled',
                'old_availability' => $result['old_availability'],
                'new_availability' => $result['new_availability'],
                'name_en' => $meal->name_en,
                'name_fr' => $meal->name_fr,
                'tenant_id' => $tenant->id,
            ])
            ->log('Meal availability changed from '.($result['old_availability'] ? 'available' : 'unavailable').' to '.($result['new_availability'] ? 'available' : 'unavailable'));

        // Toast message based on new availability
        $mealName = $meal->name;
        $toastMessage = $result['new_availability']
            ? __(':name is now available.', ['name' => $mealName])
            : __(':name is now unavailable.', ['name' => $mealName]);

        $referer = $request->header('Referer', '');
        $redirectUrl = str_contains($referer, '/meals/') && str_contains($referer, '/edit')
            ? url('/dashboard/meals/'.$meal->id.'/edit')
            : url('/dashboard/meals');

        if ($request->isGale()) {
            return gale()
                ->redirect($redirectUrl)
                ->with('success', $toastMessage);
        }

        return redirect($redirectUrl)
            ->with('success', $toastMessage);
    }

    /**
     * Delete a meal (soft delete).
     *
     * F-111: Meal Delete
     * BR-218: Soft-deleted (preserved with deleted_at timestamp)
     * BR-219: Cannot delete with pending/active orders
     * BR-220: Immediately hidden from tenant landing page
     * BR-221: Removed from cook's meal list
     * BR-224: Confirmation dialog shown before deletion (frontend)
     * BR-225: Deletion logged via Spatie Activitylog
     * BR-226: Only users with manage-meals permission
     */
    public function destroy(Request $request, int $mealId, MealService $mealService): mixed
    {
        $user = $request->user();
        $tenant = tenant();

        // BR-226: Permission check
        if (! $user->can('can-manage-meals')) {
            abort(403);
        }

        $meal = $tenant->meals()->findOrFail($mealId);

        // Use MealService for business logic
        $result = $mealService->deleteMeal($meal);

        if (! $result['success']) {
            // BR-219: Pending orders exist
            if ($request->isGale()) {
                return gale()
                    ->redirect(url('/dashboard/meals'))
                    ->back()
                    ->with('error', $result['error']);
            }

            return redirect()->back()
                ->with('error', $result['error']);
        }

        // BR-225: Activity logging
        activity('meals')
            ->performedOn($meal)
            ->causedBy($user)
            ->withProperties([
                'action' => 'meal_deleted',
                'name_en' => $meal->name_en,
                'name_fr' => $meal->name_fr,
                'status' => $meal->status,
                'tenant_id' => $tenant->id,
            ])
            ->log('Meal deleted');

        // Redirect to meal list with success toast
        if ($request->isGale()) {
            return gale()
                ->redirect(url('/dashboard/meals'))
                ->with('success', __('Meal deleted.'));
        }

        return redirect(url('/dashboard/meals'))
            ->with('success', __('Meal deleted.'));
    }
}
