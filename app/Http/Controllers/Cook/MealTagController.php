<?php

namespace App\Http\Controllers\Cook;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cook\SyncMealTagsRequest;
use App\Services\MealTagService;
use Illuminate\Http\Request;

class MealTagController extends Controller
{
    /**
     * Sync tags for a meal.
     *
     * F-114: Meal Tag Assignment
     * BR-244: Maximum 10 tags per meal
     * BR-245: Tags are assigned from the cook's existing tag list (tenant-scoped)
     * BR-246: Tag assignment is a many-to-many relationship
     * BR-247: Tags can be assigned and removed without page reload
     * BR-249: Only users with manage-meals permission can assign/remove tags
     * BR-250: Tag assignment changes are logged via Spatie Activitylog
     */
    public function sync(Request $request, int $mealId, MealTagService $mealTagService): mixed
    {
        $user = $request->user();
        $tenant = tenant();

        // BR-249: Permission check
        if (! $user->can('can-manage-meals')) {
            abort(403);
        }

        $meal = $tenant->meals()->findOrFail($mealId);

        // Dual Gale/HTTP validation pattern
        if ($request->isGale()) {
            $validated = $request->validateState([
                'selected_tag_ids' => ['present', 'array', 'max:'.MealTagService::MAX_TAGS_PER_MEAL],
                'selected_tag_ids.*' => ['integer', 'exists:tags,id'],
            ], [
                'selected_tag_ids.max' => __('Maximum :max tags per meal.', ['max' => MealTagService::MAX_TAGS_PER_MEAL]),
            ]);
        } else {
            $validated = app(SyncMealTagsRequest::class)->validated();
        }

        $tagIds = array_map('intval', $validated['selected_tag_ids'] ?? []);

        // Use MealTagService for business logic
        $result = $mealTagService->syncTags($meal, $tagIds);

        if (! $result['success']) {
            if ($request->isGale()) {
                return gale()->messages([
                    'selected_tag_ids' => $result['error'],
                ]);
            }

            return redirect(url('/dashboard/meals/'.$meal->id.'/edit'))
                ->withErrors(['selected_tag_ids' => $result['error']]);
        }

        // BR-250: Activity logging for tag changes
        if ($result['has_changes']) {
            $attachedNames = $mealTagService->getTagNames($result['changes']['attached']);
            $detachedNames = $mealTagService->getTagNames($result['changes']['detached']);

            $properties = [
                'action' => 'meal_tags_updated',
                'meal_id' => $meal->id,
                'meal_name' => $meal->name_en,
                'tenant_id' => $tenant->id,
            ];

            if (! empty($attachedNames)) {
                $properties['tags_added'] = $attachedNames;
            }
            if (! empty($detachedNames)) {
                $properties['tags_removed'] = $detachedNames;
            }

            activity('meals')
                ->performedOn($meal)
                ->causedBy($user)
                ->withProperties($properties)
                ->log('Meal tags updated');
        }

        // BR-247: Respond without page reload
        if ($request->isGale()) {
            return gale()->redirect(url('/dashboard/meals/'.$meal->id.'/edit'))
                ->with('toast', ['type' => 'success', 'message' => __('Tags updated.')]);
        }

        return redirect(url('/dashboard/meals/'.$meal->id.'/edit'))
            ->with('toast', ['type' => 'success', 'message' => __('Tags updated.')]);
    }
}
