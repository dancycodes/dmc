<?php

namespace App\Services;

use App\Models\Meal;
use App\Models\Tag;
use App\Models\Tenant;
use Illuminate\Support\Collection;

class MealTagService
{
    /**
     * BR-244: Maximum 10 tags per meal.
     */
    public const MAX_TAGS_PER_MEAL = 10;

    /**
     * Sync tags for a meal.
     *
     * BR-244: Maximum 10 tags per meal
     * BR-245: Tags are assigned from the cook's existing tag list (tenant-scoped)
     * BR-246: Tag assignment is a many-to-many relationship
     * BR-248: Removing a tag from a meal does not delete the tag itself
     *
     * @param  array<int>  $tagIds
     * @return array{success: bool, error?: string, old_tags?: array<int>, new_tags?: array<int>, changes?: array{attached: array<int>, detached: array<int>}}
     */
    public function syncTags(Meal $meal, array $tagIds): array
    {
        // BR-244: Enforce max 10 tags
        if (count($tagIds) > self::MAX_TAGS_PER_MEAL) {
            return [
                'success' => false,
                'error' => __('Maximum :max tags per meal.', ['max' => self::MAX_TAGS_PER_MEAL]),
            ];
        }

        // BR-245: Verify all tag IDs belong to the same tenant
        $tenant = $meal->tenant;
        $validTagIds = Tag::forTenant($tenant->id)
            ->whereIn('id', $tagIds)
            ->pluck('id')
            ->all();

        // Filter out any invalid tag IDs
        $tagIds = array_intersect($tagIds, $validTagIds);

        // Capture old state for activity logging
        $oldTagIds = $meal->tags()->pluck('tags.id')->all();

        // BR-246: Sync via many-to-many (attach/detach as needed)
        $syncResult = $meal->tags()->sync($tagIds);

        // Calculate what actually changed
        $attached = $syncResult['attached'] ?? [];
        $detached = $syncResult['detached'] ?? [];
        $hasChanges = ! empty($attached) || ! empty($detached);

        return [
            'success' => true,
            'old_tags' => $oldTagIds,
            'new_tags' => $tagIds,
            'changes' => [
                'attached' => $attached,
                'detached' => $detached,
            ],
            'has_changes' => $hasChanges,
        ];
    }

    /**
     * Get tag assignment data for the meal edit page.
     *
     * @return array{availableTags: Collection, assignedTagIds: array<int>, tagCount: int, maxTags: int, canAddMore: bool}
     */
    public function getTagAssignmentData(Tenant $tenant, Meal $meal): array
    {
        $availableTags = Tag::forTenant($tenant->id)
            ->orderBy(localized('name'))
            ->get();

        $assignedTagIds = $meal->tags()->pluck('tags.id')->all();
        $tagCount = count($assignedTagIds);

        return [
            'availableTags' => $availableTags,
            'assignedTagIds' => $assignedTagIds,
            'tagCount' => $tagCount,
            'maxTags' => self::MAX_TAGS_PER_MEAL,
            'canAddMore' => $tagCount < self::MAX_TAGS_PER_MEAL,
        ];
    }

    /**
     * Get the names of tags by IDs for activity logging.
     *
     * @param  array<int>  $tagIds
     * @return array<string>
     */
    public function getTagNames(array $tagIds): array
    {
        if (empty($tagIds)) {
            return [];
        }

        return Tag::whereIn('id', $tagIds)
            ->pluck('name_en')
            ->all();
    }
}
